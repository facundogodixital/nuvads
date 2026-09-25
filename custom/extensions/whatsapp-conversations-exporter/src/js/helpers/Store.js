import _ from 'lodash';
import StorageHelper from '@/js/helpers/StorageHelper';



class Store
{

  //public state

  constructor(secret = undefined)
  {
    if (secret !== 'secret') {
      throw new Error('Store cannot be called directly');
    }
    this.clearState();
  }


  static async build()
  {
    const instance = new Store('secret');
    await instance.loadFromStorage();
    return instance;
  }

  clearState()
  {
    this.state = {};
  }


  async loadFromStorage()
  {
    const state = await StorageHelper.loadFromStorage('global.state.data');
    if (state === undefined) {
      return false;
    }
    for (const propName in state) {
      this.state[propName] = state[propName];
    }
  }
  

  async saveToStorage()
  {
    await StorageHelper.saveToStorage('global.state.data', {...this.state});
  }


  set(propName, prop)
  {
    this.state[propName] = prop;
  }

  get(propName, prop)
  {
    return _.get(this.state, propName, null);
  }

  remove(propName)
  {
    if (propName in this.state) {
      delete this.state[propName];
    }
  }

}


export default Store;