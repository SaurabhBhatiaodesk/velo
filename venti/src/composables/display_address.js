export function displayAddress (address, state = false, country = false) {
  if (!address || !address.street?.length || !address.number?.length) {
    return ''
  }
  let result = `${address.street} ${address.number}`
  result += ', '
  if (address.line2 && address.line2.length) {
    result += address.line2
    result += ', '
  }
  result += address.city
  if (state && address.state && address.state.length) {
    result += ', '
    result += address.state
  }
  if (country) {
    result += ', '
    result += address.country
  }
  return result
}
