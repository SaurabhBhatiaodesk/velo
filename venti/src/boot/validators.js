import { boot } from 'quasar/wrappers'

// "async" is optional;
// more info on params: https://v2.quasar.dev/quasar-cli/boot-files
export default boot(async ({ app }) => {
  app.provide('Validators', {
    objectNotEmpty: (value) => (typeof value === 'object' && Object.keys(value).length > 0),
    alphaSpace: (value) => (!value.length || /^[A-Za-z\u0590-\u05fe\s]*$/.test(value)),
    alphaDash: (value) => (!value.length || /^[A-Za-z\u0590-\u05fe\s\-]+$/.test(value)), // eslint-disable-line
    alphaDashApostrophe: (value) => (!value.length || /^[A-Za-z\u0590-\u05fe\s\-']+$/.test(value)), // eslint-disable-line
    alphaNumDash: (value) => (!value.length || /^[0-9A-Za-z\u0590-\u05fe\s\-]+$/.test(value)), // eslint-disable-line
    numSpace: (value) => (!value.length || /^[0-9\s\-]+$/.test(value)) // eslint-disable-line
  })
})
