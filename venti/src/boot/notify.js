import { boot } from 'quasar/wrappers'
import { Notify } from 'quasar'

export default boot(async ({ app, store }) => {
  // console.log('app.config.globalProperties', app.config.globalProperties)
  function positiveNotification (message, config = {}, i18nPath = '') {
    if (i18nPath.length) {
      message = app.config.globalProperties.$t(i18nPath + '.' + message)
    }
    Notify.create(Object.assign({
      color: 'primaryGreen',
      textColor: 'white',
      message
    }, config))
  }

  function negativeNotification (message, config = {}, i18nPath = '') {
    if (i18nPath.length) {
      message = app.config.globalProperties.$t(i18nPath + '.' + message)
    }
    if (message && message.length) {
      Notify.create(Object.assign({
        color: 'primaryRed',
        textColor: 'white',
        message
      }, config))
    }
  }

  function rejected (method, e, i18nPath = 'responses') {
    console.log(`### ${method} error ${e.status}`, e.message)
    let message = ''
    if (e.message && e.message.length) {
      message = e.message
    } else if (e.error && e.error.length) {
      message = e.error
    }
    if (message.endsWith('.')) {
      message = message.slice(0, -1)
    }
    negativeNotification(message, {}, i18nPath)

    return {
      success: false,
      result: e.message
    }
  }

  store.use(() => ({
    negativeNotification,
    positiveNotification,
    rejected
  }))

  app.provide('Notifications', {
    positive: positiveNotification,
    negative: negativeNotification
  })
})
