import { Loader } from '@googlemaps/js-api-loader'
import { Quasar, Cookies } from 'quasar'
import { countries } from 'moment-timezone/data/meta/latest.json'

const languageCodes = {
  'en-US': 'en',
  he: 'iw'
}

export function loadGmapsApi () {
  return new Loader({
    apiKey: process.env.GMAPS_API_KEY,
    version: 'weekly',
    libraries: ['places'],
    language: isoToCode(Cookies.has('velo_returns.locale') ? Cookies.get('velo_returns.locale') : Quasar.lang.isoName)
  }).load()
}

export function codeToIso (code) {
  for (const iso in languageCodes) {
    if (languageCodes[iso].toLowerCase() === code.toLowerCase()) {
      return iso
    }
  }
  return 'en-US'
}

export function isoToCode (iso) {
  return (languageCodes[iso] && languageCodes[iso].length) ? languageCodes[iso] : languageCodes['en-US']
}

export function formatGmapsPlace (place) {
  return Object.assign(formatGmapsAddressComponent(place.address_components), {
    latitude: place.geometry.location.lat(),
    longitude: place.geometry.location.lng()
  })
}

export function formatGmapsAddressComponent (addressComponents) {
  const result = {
    line1: '',
    street: '',
    houseNumber: '',
    zipcode: '',
    state: '',
    city: '',
    country: ''
  }

  const shouldBeComponent = {
    line1: ['street_number', 'street_address', 'route'],
    street: ['street_address', 'route'],
    houseNumber: ['street_number'],
    zipcode: ['postal_code'],
    state: [
      'administrative_area_level_1',
      'administrative_area_level_2',
      'administrative_area_level_3',
      'administrative_area_level_4',
      'administrative_area_level_5'
    ],
    city: [
      'locality',
      'sublocality',
      'sublocality_level_1',
      'sublocality_level_2',
      'sublocality_level_3',
      'sublocality_level_4'
    ],
    country: ['country']
  }

  addressComponents.forEach(component => {
    for (const shouldBe in shouldBeComponent) {
      if (shouldBeComponent[shouldBe].indexOf(component.types[0]) !== -1) {
        if (!result[shouldBe] || !result[shouldBe].length) {
          result[shouldBe] = ''
        } else {
          result[shouldBe] += ' '
        }
        result[shouldBe] += component.long_name
      }
    }
  })
  return result
}

export function getGmapsCountry (countryName) {
  countryName = countryName.toLowerCase()
  for (const i in countries) {
    if (countries[i].name.toLowerCase() === countryName) {
      return countries[i].abbr.toLowerCase()
    }
  }
  return ''
}
