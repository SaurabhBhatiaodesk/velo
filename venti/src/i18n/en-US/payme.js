export default {
  cardNumber: 'card number',
  cardExpiration: 'expiry date',
  cvc: 'cvv',
  errors: {
    generic: 'Something went wrong',
    20004: 'Declined by credit company',
    21005: 'Incorrect card number',
    21008: 'Incorrect CVV or ID',
    354: 'Invalid CVV',
    355: 'Invalid credit card number',
    356: 'Invalid credit card expiry',
    610: 'Card is restricted'
  },
  error: 'error in {fields} | errors in {fields}',
  fields: {
    payerFirstName: 'First Name',
    payerLastName: 'Last Name',
    payerEmail: 'Email',
    payerPhone: 'Phone',
    payerSocialId: 'Social ID',
    cardNumber: 'Card Number',
    cardExpiration: 'Card Expiry',
    cvc: 'CVV'
  }
}
