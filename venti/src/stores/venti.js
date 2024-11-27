import { defineStore } from 'pinia'

export const useVentiStore = defineStore('venti', {
  state: () => ({
    nonce: '',
    call: {},
    order: {},
    customer: {},
    settingsHolder: {},
    addresses: []
  }),

  getters: {
    settings: state => Object.keys(state.settingsHolder).length > 0 ? state.settingsHolder : JSON.parse(window.veloVentiSettings)
  },

  actions: {
    getNonceRequestConfig () {
      return {
        headers: {
          'X-Velo-Api-Key': window.veloVentiApiKey,
          'X-Velo-Returns-Nonce': this.call.nonce,
          'X-Velo-Returns-Call-Id': this.call.id
        }
      }
    },

    searchOrder ({ orderName, phone }) {
      return new Promise(resolve => {
        this.api.post('venti/find_order', { orderName, phone }, this.getNonceRequestConfig()).then(({ data }) => {
          if (!data.call) {
            resolve(this.rejected('searchOrder', data))
          }
          this.call = data.call
          this.order = data.order
          this.customer = data.customer
          this.addresses = data.addresses
          this.settingsHolder = data.settings
          resolve(this.success(data))
        }).catch(e => {
          resolve(this.rejected('searchOrder', e.response.data))
        })
      })
    },

    checkReturnInfo ({ address, deliveryType, description }) {
      return new Promise(resolve => {
        this.api.post('venti/check_return_info', { address, deliveryType, description }, this.getNonceRequestConfig()).then(({ data }) => {
          this.call = data
          resolve(this.success(data))
        }).catch(e => {
          resolve(this.rejected('checkReturnInfo', e))
        })
      })
    },

    savePaymentInfo (paymeTokenizationResult = {}) {
      return new Promise(resolve => {
        this.api.post('venti/save_payment_info', {
          description: paymeTokenizationResult.label,
          holder_name: paymeTokenizationResult.card.cardholderName,
          expiry: paymeTokenizationResult.card.expiry,
          email: paymeTokenizationResult.payerEmail,
          phone: paymeTokenizationResult.payerPhone,
          social_id: paymeTokenizationResult.payerSocialId,
          token: paymeTokenizationResult.token,
          total: paymeTokenizationResult.total.amount.value,
          transaction_data: paymeTokenizationResult
        }, this.getNonceRequestConfig()).then(({ data }) => {
          this.call = data
          resolve(this.success(data))
        }).catch(e => {
          resolve(this.rejected('checkReturnInfo', e))
        })
      })
    },

    createOrder () {
      return new Promise(resolve => {
        this.api.post('venti/create_order', {}, this.getNonceRequestConfig()).then(({ data }) => {
          this.call = data
          resolve(this.success(data))
        }).catch(e => {
          resolve(this.rejected('createOrder', e))
        })
      })
    }
  }
})
