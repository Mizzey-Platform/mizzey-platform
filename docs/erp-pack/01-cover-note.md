# Mizzey online store and the ERP: stock integration

## Questions ahead of our technical meeting

* **To:** [name], ERP technical lead for Mizzey
* **Through:** [name], Mizzey
* **From:** Mustafa Shaaban, developer of the Mizzey online store
* **Date:** [date]

Dear [name],

I am building the new Mizzey online store, which takes its stock from your ERP. I would like to prepare our
technical meeting properly, so that it costs you as little time as possible.

**What is already agreed, between Mizzey and me.** Four business rules. The ERP is the source of truth for stock.
The store keeps no stock balance of its own that could disagree with it. A sale on the website reduces the stock in
the ERP. The store does not confirm a sale when the ERP cannot confirm the stock, including when the ERP cannot be
reached. It is also agreed that every product and variant in the store carries a SKU that matches the ERP exactly.
Only stock is involved: orders, customers, prices, product details and accounting are not part of this work.

**What is yours to tell me.** How. No interface, no method and no timing has been chosen. On the SKU, I am not
asking what the matching key should be. I am asking how the SKU behaves on your side: how it is looked up, how
unique and stable it is, and what happens when it changes.

**What is attached.** This note (1). A reply sheet for your answers (2). The technical appendix (3): what is fixed
on the store side, eighteen decisions to reach together (M1 to M18), the complete question list (sections 1 to 10)
and the proposed acceptance criteria (section 11). The list of material to provide (4).

**What I am asking of you.**

| When | What | By |
|---|---|---|
| Before the meeting | The material in attachment 4, and first answers on access (M2), the test environment (M3) and how a SKU is resolved (M4, M5) | [date] |
| At the meeting | The eighteen decisions, M1 to M18 | [meeting date, to be arranged] |
| After the meeting | Written answers on the reply sheet to whatever remains of sections 1 to 10 | [date] |

"We do not know yet" is a useful answer. Where something is not decided, a name and a date for it is all I need.

**What I am not asking.** I am not asking you to build anything before we have met. I am not asking for access to
the live ERP: the integration is built and tested against a test environment only. I am not asking for anything
beyond stock, and the store will not send customer personal data to the ERP.

**How your system is treated.** The ERP and its interface are yours. If the meeting shows that work is needed on
your side, it is written down with its scope and timing and agreed with Mizzey before anything depends on it.

**What happens afterwards.** I write up what we agree as the ERP Integration Specification, and I will ask you to
check that it describes your system correctly. Mizzey then reviews it and approves it in writing. The store side is
built to it only after that approval.

**How this pack relates to the earlier document.** You may already hold "ERP Technical Meeting: Preparation and
Open Decisions", dated 22 September 2026. It sets out scenarios and one possible design for discussion. Its
statement that both language versions of a product carry the same SKU is consistent with this pack. Where the two
differ, this pack is the current question list. Its questions numbered M-1 to M-8 are questions for Mizzey, and are
not the decisions M1 to M18 here.

I can be reached at [email] and [telephone]. Thank you for your time.

Kind regards,

Mustafa Shaaban
