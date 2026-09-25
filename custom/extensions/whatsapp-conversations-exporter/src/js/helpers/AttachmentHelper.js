import APICall from '@/js/helpers/APICall';


function promisifyRequest(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}


class AttachmentHelper
{

  static DEFAULT_CACHE_DAYS = 1;
  static ATTACHMENTS_DB_STORE_NAME = 'attachments';
  static ATTACHMENTS_DB_NAME = 'ClientyWapAttachments';


  static async openAttachmentDB()
  {
    const request = indexedDB.open(this.ATTACHMENTS_DB_NAME, 1);
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(this.ATTACHMENTS_DB_STORE_NAME)) {
        db.createObjectStore(this.ATTACHMENTS_DB_STORE_NAME, { keyPath: 'hash' });
      }
    };
    
    return promisifyRequest(request);
  }

  

  static async downloadAttachment(url)
  {
    try {
      // const response = await fetch(url);
      console.log('-------------------- ATTACHMENT START');
      const response = await APICall(url, 'get', { isFileDownload: true });
      console.log('-------------------- ATTACHMENT RESPONSE', response);
      if (!response.ok) {
        throw new Error(`Failed to fetch attachment: ${response.status}`);
      }
      // return await response.blob();
      return response.data;
    } catch (error) {
      console.error('[AttachmentHelper] - Error downloading attachment:', error);
      throw error;
    }
  }

  
  static async blobToBase64(blob)
  {
    const reader = new FileReader();
    reader.readAsDataURL(blob);
    
    return new Promise((resolve, reject) => {
      reader.onloadend = () => {
        const base64 = reader.result.split(',')[1];
        resolve(base64);
      };
      reader.onerror = reject;
    });
  }

  
  static async getAttachmentFromCache(hash)
  {
    try {
      const db = await this.openAttachmentDB();
      const transaction = db.transaction([this.ATTACHMENTS_DB_STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.ATTACHMENTS_DB_STORE_NAME);
      
      const result = await promisifyRequest(store.get(hash));
      return result || null;
    } catch (error) {
      console.error('[AttachmentHelper] - Error getting cache:', error);
      return null;
    }
  }


  static async saveAttachmentToCache(hash, fileBlob)
  {
    try {
      const db = await this.openAttachmentDB();
      const transaction = db.transaction([this.ATTACHMENTS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.ATTACHMENTS_DB_STORE_NAME);
      
      const record = {
        hash,
        blob: fileBlob,
        timestamp: Date.now(),
      };
      
      await promisifyRequest(store.put(record));
      return true;
    } catch (error) {
      console.error('[AttachmentHelper] - Error saving to DB:', error);
      return false;
    }
  }


  static async cleanOldAttachmentCache(daysMaxAge = this.DEFAULT_CACHE_DAYS)
  {
    try {
      const db = await this.openAttachmentDB();
      const transaction = db.transaction([this.ATTACHMENTS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.ATTACHMENTS_DB_STORE_NAME);
      
      const allRecords = await promisifyRequest(store.getAll());
      const cutoffTime = Date.now() - (daysMaxAge * 24 * 60 * 60 * 1000);
      
      let deletedCount = 0;
      for (const record of allRecords) {
        if (record.timestamp < cutoffTime) {
          await promisifyRequest(store.delete(record.hash));
          deletedCount++;
        }
      }
      
      console.log(`[AttachmentHelper] - Cache cleanup completed. Deleted ${deletedCount} old entries.`);
    } catch (error) {
      console.error('[AttachmentHelper] - Error cleaning cache:', error);
    }
  }


  static async getOrDownloadAttachment(hash, url)
  {
    try {
      // First, try to get from cache
      let cachedAttachment = await this.getAttachmentFromCache(hash);
      
      if (cachedAttachment && cachedAttachment.blob) {
        console.log(`[AttachmentHelper] - Using cached attachment for hash: ${hash}`);
        return {
          fromCache: true,
          // blob: cachedAttachment.blob,
          base64: await this.blobToBase64(cachedAttachment.blob),
        };
      }

      console.log(`[AttachmentHelper] - Downloading attachment from: ${url}`);
      const blob = await this.downloadAttachment(url);
      
      await this.saveAttachmentToCache(hash, blob);
      
      return {
        // blob: blob,
        fromCache: false,
        base64: await this.blobToBase64(blob),
      };
    } catch (error) {
      console.error('[AttachmentHelper] - Error in getOrDownloadAttachment:', error);
      throw error;
    }
  }

  
  static async getCacheSize() {
    try {
      const db = await this.openAttachmentDB();
      const transaction = db.transaction([this.ATTACHMENTS_DB_STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.ATTACHMENTS_DB_STORE_NAME);
      
      const allRecords = await promisifyRequest(store.getAll());
      let totalSize = 0;
      
      for (const record of allRecords) {
        if (record.blob && record.blob.size) {
          totalSize += record.blob.size;
        }
      }
      return totalSize;
    } catch (error) {
      console.error('[AttachmentHelper] - Error calculating cache size:', error);
      return 0;
    }
  }

  
  static async clearAllCache() {
    try {
      const db = await this.openAttachmentDB();
      const transaction = db.transaction([this.ATTACHMENTS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.ATTACHMENTS_DB_STORE_NAME);
      
      await promisifyRequest(store.clear());
      console.log('[AttachmentHelper] - All cache cleared successfully');
      return true;
    } catch (error) {
      console.error('[AttachmentHelper] - Error clearing cache:', error);
      return false;
    }
  }

}

export default AttachmentHelper;