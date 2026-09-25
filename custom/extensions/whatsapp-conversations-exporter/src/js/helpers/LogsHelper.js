function promisifyRequest(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}


class LogsHelper
{

  static DEFAULT_LOGS_MAX_AGE_DAYS = 7;
  static LOGS_DB_STORE_NAME = 'logs';
  static LOGS_DB_NAME = 'ClientyWapLogs';


  static async openLogDB()
  {
    const request = indexedDB.open(this.LOGS_DB_NAME, 1);
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(this.LOGS_DB_STORE_NAME)) {
        const store = db.createObjectStore(this.LOGS_DB_STORE_NAME, { keyPath: 'id', autoIncrement: true });
        store.createIndex('timestamp', 'timestamp', { unique: false });
      }
    };
    
    return promisifyRequest(request);
  }

  
  static async save(message, context = {})
  {
    try {
      const db = await this.openLogDB();
      const transaction = db.transaction([this.LOGS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.LOGS_DB_STORE_NAME);
      
      const record = {
        message,
        context,
        timestamp: new Date(),
      };
      
      await promisifyRequest(store.put(record));
      return true;
    } catch (error) {
      console.error('[LogsHelper] - Error adding log:', error);
      return false;
    }
  }


  static async getAllLogs()
  {
    try {
      const db = await this.openLogDB();
      const transaction = db.transaction([this.LOGS_DB_STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.LOGS_DB_STORE_NAME);
      
      const result = await promisifyRequest(store.getAll());
      return result || [];
    } catch (error) {
      console.error('[LogsHelper] - Error getting all logs:', error);
      return [];
    }
  }


  static async clearOldLogs(daysMaxAge = this.DEFAULT_LOGS_MAX_AGE_DAYS)
  {
    try {
      const db = await this.openLogDB();
      const transaction = db.transaction([this.LOGS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.LOGS_DB_STORE_NAME);
      const index = store.index('timestamp');

      const cutoffTime = new Date(Date.now() - (daysMaxAge * 24 * 60 * 60 * 1000));
      
      const keyRange = IDBKeyRange.upperBound(cutoffTime);
      const request = index.openCursor(keyRange);

      let deletedCount = 0;
      return new Promise((resolve, reject) => {
        request.onsuccess = event => {
          const cursor = event.target.result;
          if (cursor) {
            store.delete(cursor.primaryKey);
            deletedCount++;
            cursor.continue();
          } else {
            console.log(`[LogsHelper] - Log cleanup completed. Deleted ${deletedCount} old entries.`);
            resolve(deletedCount);
          }
        };
        request.onerror = event => {
           console.error('[LogsHelper] - Error cleaning old logs:', event.target.error);
           reject(event.target.error);
        }
      });
    } catch (error) {
      console.error('[LogsHelper] - Error cleaning logs:', error);
    }
  }

  
  static async clearAllLogs() {
    try {
      const db = await this.openLogDB();
      const transaction = db.transaction([this.LOGS_DB_STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.LOGS_DB_STORE_NAME);
      
      await promisifyRequest(store.clear());
      console.log('[LogsHelper] - All logs cleared successfully');
      return true;
    } catch (error) {
      console.error('[LogsHelper] - Error clearing logs:', error);
      return false;
    }
  }

}

export default LogsHelper; 