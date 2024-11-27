import { boot } from 'quasar/wrappers'
import { Cookies } from 'quasar'
import axios from 'axios'

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
export default boot(({ app, store, ssrContext }) => {
  const cookies = process.env.SERVER ? Cookies.parseSSR(ssrContext) : Cookies
  const api = axios.create({
    baseURL: process.env.SERVER_ROOT.endsWith('/') ? (process.env.SERVER_ROOT + 'api') : (process.env.SERVER_ROOT + '/api'),
    withCredentials: false
  })

  app.provide('api', api)
  app.provide('cookies', cookies)
  store.use(() => ({ api, cookies }))
})
