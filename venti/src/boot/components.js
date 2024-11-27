import { boot } from 'quasar/wrappers'
import CardsSelect from 'src/components/_global/CardsSelect.vue'
import ChatBtn from 'src/components/_global/ChatBtn.vue'
import DrawerDialog from 'src/components/_global/DrawerDialog.vue'
import LoadingOverlay from 'src/components/_global/LoadingOverlay.vue'
import LottieAnimation from 'src/components/_global/LottieAnimation.vue'
import PersonAvatar from 'src/components/_global/PersonAvatar.vue'
import PopupDialog from 'src/components/_global/PopupDialog.vue'
import PromptDialog from 'src/components/_global/PromptDialog.vue'
import SvgImg from 'src/components/_global/SvgImg.vue'
import VeloBtn from 'src/components/_global/VeloBtn.vue'
import VeloIcon from 'src/components/_global/VeloIcon.vue'
import VeloSpinner from 'src/components/_global/VeloSpinner.vue'

export default boot(({ app }) => {
  // Global Components
  app.component('CardsSelect', CardsSelect)
  app.component('DrawerDialog', DrawerDialog)
  app.component('LoadingOverlay', LoadingOverlay)
  app.component('LottieAnimation', LottieAnimation)
  app.component('PopupDialog', PopupDialog)
  app.component('PromptDialog', PromptDialog)
  app.component('PersonAvatar', PersonAvatar)
  app.component('SvgImg', SvgImg)
  app.component('ChatBtn', ChatBtn)
  app.component('VeloBtn', VeloBtn)
  app.component('VeloIcon', VeloIcon)
  app.component('VeloSpinner', VeloSpinner)
})
