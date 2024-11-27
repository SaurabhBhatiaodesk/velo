import { boot } from 'quasar/wrappers'
import { createI18n } from 'vue-i18n'
import { Quasar, Cookies } from 'quasar'
import messages from 'src/i18n'

export default boot(({ app, ssrContext }) => {
  const cookies = process.env.SERVER ? Cookies.parseSSR(ssrContext) : Cookies

  const i18n = createI18n({
    locale: (cookies.has('velo_returns.locale')) ? cookies.get('velo_returns.locale') : Quasar.lang.isoName,
    legacy: false,
    globalInjection: true,
    allowComposition: true,
    messages
  })

  app.use(i18n)
})
