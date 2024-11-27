import { EventBus } from 'quasar'
import { boot } from 'quasar/wrappers'

const bus = new EventBus()

export default boot(({ app }) => {
  app.provide('Bus', bus)
})

export { bus }
