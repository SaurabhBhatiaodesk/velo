import { boot } from 'quasar/wrappers'

export default boot(({ store }) => {
  function getItem (indexVal, collection, indexCol = 'id') {
    for (const i in collection) {
      if (collection[i][indexCol] === indexVal) {
        return collection[i]
      }
    }
    return null
  }

  function setItem (item, collection, indexCol = 'id') {
    for (const i in collection) {
      if (collection[i][indexCol] === item[indexCol]) {
        collection[i] = item
        return collection
      }
    }
    if (Array.isArray(collection)) {
      collection.push(item)
    } else {
      collection[item[indexCol]] = item
    }
    return collection
  }

  function removeItem (item, collection, indexCol = 'id') {
    for (const i in collection) {
      if (collection[i][indexCol] === item[indexCol]) {
        collection.splice(i, 1)
        return collection
      }
    }
    return collection
  }

  function removeItems (itemsArray, collection, indexCol = 'id') {
    for (const i in itemsArray) {
      for (const j in collection) {
        if (collection[j][indexCol] === itemsArray[i][indexCol]) {
          collection.splice(j, 1)
          break
        }
      }
    }
    return collection
  }

  function setItems (items, collection, indexCol = 'id') {
    for (const i in items) {
      collection = setItem(items[i], collection, indexCol)
    }
    return collection
  }

  function success (result) {
    return {
      success: true,
      result
    }
  }

  function getPropFromDotNotationString (obj, prop) {
    if (typeof obj === 'undefined' || obj === null) {
      return false
    }
    const index = prop.indexOf('.')
    if (index > -1) {
      return getPropFromDotNotationString(obj[prop.substring(0, index)], prop.substr(index + 1))
    }
    return obj[prop]
  }

  function deletePropFromDotNotationString (obj, prop) {
    if (typeof obj === 'undefined' || obj === null) {
      return false
    }
    const index = prop.indexOf('.')
    if (index > -1) {
      obj[prop.substring(0, index)] = deletePropFromDotNotationString(obj[prop.substring(0, index)], prop.substr(index + 1))
    }
    delete obj[prop]
    return obj
  }

  function printHtml (html, title) {
    const win = window.open('', title)
    win.document.body.innerHTML = html
    win.focus()
    win.print()
  }

  store.use(() => ({
    getItem,
    setItem,
    setItems,
    removeItem,
    removeItems,
    success,
    getPropFromDotNotationString,
    deletePropFromDotNotationString,
    printHtml
  }))
})
