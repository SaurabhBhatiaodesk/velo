import { Quasar, Cookies } from 'quasar'
import { boot } from 'quasar/wrappers'
const langList = import.meta.glob('../../node_modules/quasar/lang/*.mjs')

// more info on params: https://v2.quasar.dev/quasar-cli/boot-files
export default boot(({ ssrContext }) => {
  const cookies = process.env.SERVER ? Cookies.parseSSR(ssrContext) : Cookies
  let langIso = 'en-US'
  if (cookies.has('velo_returns.locale')) {
    langIso = cookies.get('velo_returns.locale')
  }

  try {
    langList[`../../node_modules/quasar/lang/${langIso}.mjs`]().then(lang => {
      Quasar.lang.set(lang.default, ssrContext)
      // if (!ssrContext && window && window.Tawk_API) {
      //   const tawkPosition = {
      //     position: (lang.rtl) ? 'bl' : 'br'
      //   }
      //   window.Tawk_API.customStyle = {
      //     visibility: {
      //       desktop: tawkPosition,
      //       mobile: tawkPosition,
      //       bubble: tawkPosition
      //     }
      //   }
      // }
    })
  } catch (err) {
    console.log({ err })
  // Requested Quasar Language Pack does not exist,
  // let's not break the app, so catching error
  }
})
