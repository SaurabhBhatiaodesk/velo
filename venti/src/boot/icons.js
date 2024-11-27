import { boot } from 'quasar/wrappers'
import add from 'src/assets/icons/add.svg?raw'
import addContact from 'src/assets/icons/add_contact.svg?raw'
import bell from 'src/assets/icons/bell.svg?raw'
import box from 'src/assets/icons/box.svg?raw'
import calendar from 'src/assets/icons/calendar.svg?raw'
import chat from 'src/assets/icons/chat.svg?raw'
import close from 'src/assets/icons/close.svg?raw'
import cloudDownload from 'src/assets/icons/cloud_download.svg?raw'
import creditCard from 'src/assets/icons/credit_card.svg?raw'
import diagram from 'src/assets/icons/diagram.svg?raw'
import doneAll from 'src/assets/icons/done_all.svg?raw'
import done from 'src/assets/icons/done.svg?raw'
import download from 'src/assets/icons/download.svg?raw'
import edit from 'src/assets/icons/edit.svg?raw'
import guard from 'src/assets/icons/guard.svg?raw'
import inbox from 'src/assets/icons/inbox.svg?raw'
import inventory from 'src/assets/icons/inventory.svg?raw'
import key from 'src/assets/icons/key.svg?raw'
import like from 'src/assets/icons/like.svg?raw'
import list from 'src/assets/icons/list.svg?raw'
import logout from 'src/assets/icons/logout.svg?raw'
import mdBag from 'src/assets/icons/md_bag.svg?raw'
import menu from 'src/assets/icons/menu.svg?raw'
import normalDelivery from 'src/assets/icons/normal_delivery.svg?raw'
import pricing from 'src/assets/icons/pricing.svg?raw'
import print from 'src/assets/icons/print.svg?raw'
import question from 'src/assets/icons/question.svg?raw'
import removeContact from 'src/assets/icons/remove_contact.svg?raw'
import replacementDelivery from 'src/assets/icons/replacement_delivery.svg?raw'
import restore from 'src/assets/icons/restore.svg?raw'
import returnDelivery from 'src/assets/icons/return_delivery.svg?raw'
import search from 'src/assets/icons/search.svg?raw'
import settings from 'src/assets/icons/settings.svg?raw'
import smBag from 'src/assets/icons/sm_bag.svg?raw'
import sort from 'src/assets/icons/sort.svg?raw'
import store from 'src/assets/icons/store.svg?raw'
import toggle from 'src/assets/icons/toggle.svg?raw'
import truck from 'src/assets/icons/truck.svg?raw'
import undo from 'src/assets/icons/undo.svg?raw'
import users from 'src/assets/icons/users.svg?raw'
import velo from 'src/assets/icons/velo.svg?raw'

export default boot(({ app }) => {
  const icons = {
    add,
    addContact,
    bell,
    calendar,
    chat,
    close,
    cloudDownload,
    creditCard,
    diagram,
    doneAll,
    done,
    download,
    edit,
    guard,
    inbox,
    inventory,
    key,
    like,
    list,
    logout,
    mdBag,
    menu,
    normalDelivery,
    box,
    pricing,
    print,
    question,
    removeContact,
    replacementDelivery,
    restore,
    returnDelivery,
    search,
    settings,
    smBag,
    sort,
    store,
    toggle,
    truck,
    undo,
    users,
    velo
  }

  function iconSrc (name, color) {
    if (!icons[name]) {
      console.log('no such icon', name)
      name = 'velo'
    }
    return icons[name].split('###FILL###').join(color)
  }

  app.provide('iconSrc', iconSrc)
  app.provide('icons', Object.keys(icons))
})
