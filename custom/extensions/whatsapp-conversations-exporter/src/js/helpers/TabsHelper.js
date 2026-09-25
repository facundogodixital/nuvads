import _ from 'lodash';


const getCurrentTab = async () => {
  const tabs = await chrome.tabs.query({active: true, currentWindow: true});
  return tabs[0];
}

const currentTabIsWhatsApp = async () => {
  const currentTab = await getCurrentTab();
  const currentUrl = _.get(currentTab, 'url', '');
  return currentUrl.indexOf('//web.whatsapp.com/') !== -1;
}

const currentTabIsClienty = async () => {
  const currentTab = await getCurrentTab();
  const currentUrl = _.get(currentTab, 'url', '');
  return currentUrl.indexOf('.clienty.') !== -1;
}

const whatsAppTabIsOpened = async () => {
  const wapTab = await getWhatsAppTab();
  return wapTab ? true : false;
};
const clientyTabIsOpened = async () => {
  const wapTab = await getClientyTab();
  return wapTab ? true : false;
};

const getWhatsAppTab = async () => {
  const tabs = await chrome.tabs.query({});
  const wapTab = tabs.find(tab => tab.url.indexOf('.whatsapp.com') !== -1);
  return wapTab ? wapTab : null;
};
const getClientyTab = async () => {
  const tabs = await chrome.tabs.query({});
  const clientyTabs = await getClientyTabs({sortedByIds: true});
  const clientyTab = _.head(clientyTabs) ?? null;
  return clientyTab;
};
const getClientyTabs = async ({ sortedByIds = false, uniqueDomain = false } = {}) => {
  const tabs = await chrome.tabs.query({});
  let clientyTabs = tabs
    .filter(tab => tab.url.indexOf('.clienty.') !== -1)
    .filter(tab => tab.url.indexOf('/api/test') === -1) // Así puedo usar el TestController
  ;
  if (sortedByIds) {
    clientyTabs = _.orderBy(clientyTabs, ['id'], ['asc']);
  }
  if (uniqueDomain) {
    clientyTabs = _.uniqBy(clientyTabs, (tab) => {
      const urlArr = tab.url.split('/');
      const baseUrl = urlArr[0] + '//' + urlArr[2];
      return baseUrl;
    });
  }
  return clientyTabs;
};




const TabsHelper = {
  getCurrentTab,
  getClientyTab,
  getClientyTabs,
  getWhatsAppTab,
  clientyTabIsOpened,
  whatsAppTabIsOpened,
  currentTabIsClienty,
  currentTabIsWhatsApp,
};
export default TabsHelper;