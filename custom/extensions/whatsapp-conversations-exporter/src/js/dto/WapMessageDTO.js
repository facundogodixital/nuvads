import _ from 'lodash';

let lastId = 0;

class WapMessageDTO
{

  constructor({phoneNumber, chatMessage})
  {
    lastId++;
    this.id = lastId;
    this.sent = false;
    this.error = null;
    this.success = null;
    this.sentDateTs = null;
    this.phoneNumber = phoneNumber;
    this.chatMessage = chatMessage;
    this.createdDateTs = new Date().getTime();
  }


  static buildCollection({ phoneNumbers, chatMessage })
  {
    const collection = [];
    for (const phoneNumber of phoneNumbers) {
      const dto = new WapMessageDTO({phoneNumber, chatMessage});
      collection.push(dto);
    }
    return collection;
  }

}


export default WapMessageDTO;