
const loadFromStorage = async (key) => {
  return new Promise((resolve, reject) => {
    chrome.storage.local.get([key], function (result) {
      resolve(result[key]);
    });
  });
};


const saveToStorage = async (key, value) => {
  return new Promise((resolve, reject) => {
    const dataToStore = {};
    dataToStore[key] = value;
    chrome.storage.local.set(dataToStore, function () {
      resolve();
    });
  });
};


const clearFromStorage = async (key) => {
  return new Promise((resolve, reject) => {
    chrome.storage.local.remove([key], function () {
      resolve();
    });
  });
};



const StorageHelper = {
  saveToStorage,
  loadFromStorage,
  clearFromStorage,
};
export default StorageHelper;