# Settlement: paid is not received

**Register**: PAY-19, "Settlement timing documented: paid status is distinct from funds received". See
`specs/028-payments`.

## What the store's statuses mean

| Store shows | Means | Does not mean |
|---|---|---|
| Confirmed (or Preparing and later) after a card or wallet payment | Paymob approved the payment and told the store so, by a signed notice the store verified | That the money is in the client's bank account |
| Payment Failed | Paymob reported the payment declined or failed | Anything was charged |
| Pending Payment | No verified payment yet | The customer did not pay: a payment may still be confirming |
| Refunded, or a refund line on the order | The refund was made through Paymob (or recorded from Paymob's dashboard) | That the customer's bank has returned it yet |

The order screen's **Payment record** lists each verified event with Paymob's reference, amount, currency and time.
Any item Paymob reported that the store did not apply (another amount, a second payment, a payment for a cancelled
order) is shown there in red for staff to resolve.

## When the money arrives

Paymob collects card and wallet payments and pays them out to the merchant's bank account in settlements. Paymob's
public description of its WooCommerce integration gives settlement as **T+1 for most payment methods** (the next
working day after the transaction), with instant settlement only in some markets. The exact timing, fees and any
holding period for Mizzey are set by the client's merchant agreement with Paymob, which waits on the account approval
(CR-01). This note is updated with those terms once the account is approved.

Refunds are paid back from the merchant's Paymob balance. A refund can be refused by Paymob when the amount left on
the payment is smaller than asked (tested on the sandbox, 1 November 2026).

## How staff reconcile

1. Paymob's dashboard lists each settlement and the transactions in it.
2. Each transaction's reference matches the reference in the order's Payment record.
3. Cash on delivery follows its own path: collected is not remitted (SHIP-16, the COD Collected, Awaiting Remittance
   status).
