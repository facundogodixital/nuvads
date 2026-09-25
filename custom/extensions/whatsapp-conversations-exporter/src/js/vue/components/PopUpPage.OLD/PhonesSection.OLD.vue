<template>
  <div class="phones-section mt-2">
    <b-row>
      <b-col cols="12" class="phones-section-legend">
        <i class="bi bi-phone-fill phones-section-icon" />
        <span>Números destinatarios:</span>
      </b-col>
    </b-row>


    <b-row>
      <b-col cols="12" :title="tooltipMessage">
        <b-form-tags 
          size="xs"
          class="mb-2"
          placeholder=""
          remove-on-delete
          invalid-tag-text=""
          @paste="handlePaste"
          tag-variant="primary"
          duplicate-tag-text=""
          ref="phonesContainer"
          @input="handleChange"
          v-model="phoneNumbers"
          :disabled="isDisabled"
          input-id="new-tag-input"
          v-b-tooltip.hover.bottom
          @keypress="handleKeyPress"
          @tag-state="handleOnPhoneState"
          :tag-validator="phoneValidator"
          tag-remove-label="Quitar teléfono"
          :input-attrs="{'autocomplete': 'off'}"
          title="Copia los números desde Clienty y pégalos aquí"
        />
      </b-col>
    </b-row>
  </div>
</template>


<script>
  import _ from 'lodash';
  import jQuery from 'jquery';
  import { mapGetters, mapActions } from 'vuex';


  export default {
    name: 'PhonesSection',
    data() {
      return {
        phonesMap: {},
        phoneNumbers: [],
      };
    },
    computed: {
      ...mapGetters({
        storePhonesMap: 'popup/phonesMap',
        currentSending: 'popup/currentSending',
      }),
      isDisabled() {
        return this.currentSending ? true : false;
      },
      tooltipMessage() {
        return this.isDisabled ? 'No puedes editar los números mientras haya un envío en proceso' : '';
      },
    },
    methods: {
      ...mapActions({
        setPhonesMap: 'popup/setPhonesMap',
        savePhonesMapToStorage: 'popup/savePhonesMapToStorage',
      }),
      emitPhones() {
        this.setPhonesMap({...this.phonesMap});
        this.savePhonesMapToStorage();
      },
      isValidPhone(formattedPhoneNumber) {
        return formattedPhoneNumber.length > 8;
      },
      formatPhoneNumber(phoneNumber) {
        const onlyNumbersStr = phoneNumber.replace(/\D/g, '');
        return `${onlyNumbersStr}`;
      },
      buildPhonesMapFromPastedJSON(pastedJSONArray) {
        const phonesMapArray = _.chain(pastedJSONArray)
          .filter(phoneObj => {
            const hasAttrs = 
              _.get(phoneObj, 'leadId', null) &&
              _.get(phoneObj, 'phoneNumber', null) && 
              _.get(phoneObj, 'leadContactPhoneId', null)
            ;
            return hasAttrs ? true : false;
          })
          .map(phoneObj => {
            return {...phoneObj, phoneNumber: this.formatPhoneNumber(phoneObj.phoneNumber)};
          })
          .filter(phoneObj => this.isValidPhone(phoneObj.phoneNumber))
          .uniqBy(phoneObj => phoneObj.phoneNumber)
          .value()
        ;
        // for (const inputPhonesStr of inputArr) {
        //   const phonesStr = inputPhonesStr.trim().toLowerCase();
        //   const phonesArr = phonesStr.split(' ');
        //   for (const phoneStr of phonesArr) {
        //     const arr = phoneStr.split(':');
        //     const hasLeadId = arr.length > 1;
        //     const leadId = hasLeadId ? arr[arr.length - 1] : null;
        //     const phoneNumber = hasLeadId ? arr[0] : arr[arr.length - 1];
        //     const formattedPhoneNumber = this.formatPhoneNumber(phoneNumber);
        //     const isValidPhone = this.isValidPhone(formattedPhoneNumber);
        //     if (!isValidPhone) {
        //       continue;
        //     }
        //     phonesMap[formattedPhoneNumber] = {phoneNumber: formattedPhoneNumber, leadId};
        //   }
        // }
        const phonesMap = {};
        for (const phoneObj of phonesMapArray) {
          phonesMap[phoneObj.phoneNumber] = {...phoneObj};
        }
        return phonesMap;
      },
      handleKeyPress(event) {
        event.preventDefault();
        return false;
      },
      getPastedJSONArrayFromEvent(event) {
        const pastedStr = (event.clipboardData || window.clipboardData).getData('text');
        try {
          const pastedJSONArray = JSON.parse(pastedStr);
          return pastedJSONArray;
        } catch(err) {
          console.log('getPastedJSONArrayFromEvent Error', err);
          return null;
        }
      },
      handlePaste(event) {
        // JSON.parse([{"leadId":1620565,"leadContactPhoneId":1378639,"phoneNumber":"543425679769"},{"leadId":1620568,"leadContactPhoneId":1378642,"phoneNumber":"573222690827"},{"leadId":1620621,"leadContactPhoneId":1378695,"phoneNumber":"56966300861"},{"leadId":1620622,"leadContactPhoneId":1378696,"phoneNumber":"59178121554"}])
        event.stopPropagation();
        event.preventDefault();

        const pastedJSONArray = this.getPastedJSONArrayFromEvent(event);
        
        const phonesMap = this.buildPhonesMapFromPastedJSON(pastedJSONArray);
        this.phonesMap = {...this.phonesMap, ...phonesMap};
        this.phoneNumbers = [...Object.keys(phonesMap)];
        this.emitPhones();
      },
      async handleChange(remainingPhoneNumbers) {
        const previousPhonesMap = {...this.phonesMap};
        const previousPhonesMapCount = Object.keys(previousPhonesMap).length;
        const remainingPhoneNumbersCount = remainingPhoneNumbers.length;
        const isDeleting = remainingPhoneNumbersCount < previousPhonesMapCount;
        
        if (isDeleting) {
          const newPhonesMap = {};
          for (const remainingPhoneNumber of remainingPhoneNumbers) {
            newPhonesMap[remainingPhoneNumber] = {...previousPhonesMap[remainingPhoneNumber]};
          }
          this.phonesMap = {...newPhonesMap};
          this.phoneNumbers = [...Object.keys(newPhonesMap)];
          this.emitPhones();
        }
      },
      phoneValidator(newPhoneNumber) {
        return true;
      },
      handleOnPhoneState(validPhones, invalidPhones, duplicatedPhones) {
      },
    },
    watch: {
      storePhonesMap: {
        immediate: true,
        async handler(storePhonesMap) {
          this.phonesMap = {...storePhonesMap};
          this.phoneNumbers = [...Object.keys(this.phonesMap)];
        },
      }
    },
  }
</script>


<style>
  .phones-section .b-form-tags-button {
    display: none;
  }
  .phones-section .b-form-tag-content {
    padding: 0.1em;
    COLOR: #555555;
    font-weight: 600;
    font-size: 1.2em;
  }
  .phones-section .b-form-tag-remove {
    color: #666666;
    font-size: 1.3em;
    margin-left: 0.2em;
  }
  .phones-section .badge-primary {
    background-color: #dddddd;
    margin: 0em 0.2em 0em 0.2em;
  }
</style>


<style scoped>
  .phones-section-legend {
    font-size: 0.9em;
    color: #207EBC;
  }
</style>

