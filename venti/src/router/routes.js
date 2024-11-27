const routes = [
  {
    path: '/',
    component: () => import('layouts/App.vue'),
    redirect: { name: 'findOrder' },
    children: [
      {
        path: 'order',
        name: 'findOrder',
        component: () => import('pages/OrderSelect.vue')
      },
      {
        path: 'delivery',
        name: 'deliverySelect',
        component: () => import('pages/DeliverySelect.vue')
      },
      {
        path: 'summary',
        name: 'summary',
        component: () => import('pages/Summary.vue')
      },
      {
        path: 'payment',
        name: 'payment',
        component: () => import('pages/PaymentForm.vue')
      },
      {
        path: 'thank-you',
        name: 'thankYou',
        component: () => import('pages/ThankYou.vue')
      }
    ]
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    redirect: { name: 'findOrder' }
  }
]

export default routes
