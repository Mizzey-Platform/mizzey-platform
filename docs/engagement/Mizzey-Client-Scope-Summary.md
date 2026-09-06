# Mizzey.com
## What We Are Building

**Document:** MS-DOC-2026-002
**Version:** 1.0
**Date:** 30 August 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Companion document:** Feature Register & Scope Definition, MS-ANX-2026-001 (Contract Annex A)

---

## How to read this document

This is a summary of the store we are building for you, written in plain language.

It has three jobs. It tells you what your customers will be able to do. It tells you what you will be able to do without calling a developer. And it tells you honestly what is not included, so nothing is a surprise later.

Behind this summary sits a detailed technical register, the Feature Register MS-ANX-2026-001, listing every single requirement with a reference number. **Read this summary first. The Feature Register is the detailed contractual definition of scope and should also be reviewed before you sign.** This summary exists to make that review easier: it explains in plain language what the register defines precisely, so you know what you are looking at when you read it.

Please read Section 6 carefully. It is the part most likely to contain something you expected and are not getting.

### Where all of this comes from

Three different things sit in this document, and it is worth knowing which is which.

**Things you asked for.** Most of what follows comes from your requirements document. That document
remains the master list of what you want, and nothing in it has quietly disappeared here: where
something is arriving later, or in a reduced form, or not at all, Section 6 says so.

**Things we are recommending, and things we have decided.** Your requirements document left several
decisions to us, and asked us to propose an answer. They are not all the same kind of thing.

**Paymob for payments and Bosta for shipping are recommendations.** They are our proposals, **not
decisions you have already taken**, and both remain subject to your approval. You can change either
before signature.

**The technology stack is our engineering decision.** WordPress with WooCommerce, and Corex as our
framework layer on top of WooCommerce, are decisions we have taken, made where your requirements
asked us to propose and justify the architecture. Section 5 explains both, and says which is which.

**Things we added.** Some of what you are getting was never in your requirements document at all. We
added it because we judged it necessary. Signing in with Google, the cart that survives every path a
customer takes, the operations screen, the storefront controls, the import tool and several
protective rules are all ours. They are in the contract exactly as firmly as everything else.

**And things that are still yours to decide.** Section 8 lists them.

---

# 1. What Mizzey will be

A premium online store selling a curated, continuously refreshed range of products: trending items, gadgets that simplify daily life, imported and premium goods, gifts, and high value picks.

It is built for customers who will pay for quality, convenience and trust, and who want to find good things quickly without hunting.

Three things define it:

**It is a store, not a marketplace.** You sell your own products. There are no external sellers.

**It is premium, not discount.** The visual treatment, the product presentation and even the way offers are shown are designed to build trust in authenticity, not to shout about price.

**It runs on your terms.** You add products, change prices, run offers, edit the home page and process orders yourself. You should not need a developer for day to day business.

---

# 2. What your customers will be able to do

Mizzey launches as a **multilingual English and Arabic store**. **English is the primary and default storefront language**, and the interface is designed English-first. **Arabic is fully supported at launch** with a complete right to left version of the same customer experience: the same pages, the same functions, proper right to left layouts and Arabic typography. Arabic is not a later phase and not a reduced version.

### Finding products

Search that understands Arabic, including common misspellings and Franco Arabic. Suggestions as they type. Filters by category, brand, price, size, colour and availability. Sorting by newest, price or best selling. Browsing by category, brand or curated collection.

When a search finds nothing, they get useful alternatives instead of a dead end.

### Deciding to buy

Product pages with an image gallery and zoom, video, variant selection, live stock status, delivery estimate, your authenticity guarantee, the returns policy, customer reviews, related products, and recently viewed items.

Out of stock products are handled clearly rather than silently disappearing.

### Buying

Add to cart, or buy now, or save to wishlist. The cart shows a running total and tells them **how close they are to free shipping**, which is your main tool for raising order value.

They can check out as a guest. Or register and receive the welcome discount. Or **sign in with Google in one tap**.

**Whatever path they take, their cart follows them.** If they fill a cart as a guest and then create an account, nothing is lost. If they sign in halfway through, their old cart and their new cart merge without duplicates. This sounds small and it is one of the most common places online stores lose sales.

Checkout uses proper Egyptian address structure: governorate, city, district, street, landmark.

Checkout will support **the payment methods approved for launch**. Our recommendation for electronic payments is Paymob, and the methods actually enabled, cards and mobile wallets for example, depend on what you select and what your payment provider approves for your account. **Cash on delivery is built as a capability but is a business decision that is still yours to take**, so this document does not assume it. Section 8 lists what is still to decide.

### After buying

Order confirmation, then a tracking timeline as the parcel moves. Email at every stage. Order history in their account. Saved addresses.

**And a full returns process.** They request a return from their account, pick the items and reason, attach photos if needed, and watch its status. You review and approve or reject it. The refund goes back automatically to how they paid, and they are notified.

---

# 3. What you will be able to do

This is the part that decides whether the store actually works for you, so please read it closely.

### Your daily operations screen

A single page built for the work you do every day. It shows:

* Orders that need action, worked from top to bottom
* Stock that needs attention: low, out of stock, missing an image, unpublished
* Returns waiting for your decision
* Today's orders, revenue and units

The idea is that you open one screen in the morning and it tells you what to do.

### Managing products

Add a product in under three minutes. Edit any field on any product individually: name, description, specifications, images, price, stock, category, brand, SEO. Maintain English and Arabic separately.

Change prices or stock across many products at once, either in bulk or by editing them directly in a list without opening each one.

Mark products as New, Trending, Limited or Best Seller. Feature them. Choose which collections they appear in and in what order. Hide a product without deleting it.

Find any product in seconds by name, code or barcode.

### Managing orders

Search by order number, customer, **phone number**, status, date or carrier. Change statuses in bulk. Send many orders to the courier at once and print all the shipping labels together. Print packing slips. Export orders to Excel.

### Managing the storefront

**Everything a customer reads is yours to change.** Home page sections, their content and their order. Category banners and descriptions. Brand pages. Collections. The menu. The footer. Promotional banners. Policy pages. FAQ. Campaign landing pages.

You can preview any change before it goes live.

### Managing offers

Percentage or fixed discounts, or free shipping. Conditions on cart value, item count, specific products, categories, brands, or first order only. Automatic offers or coupon codes. Usage limits, per customer or in total. Start and end dates. Exclusions and maximum caps.

**Your two launch rules are built as settings, not as code**: the welcome discount on registration, and free shipping on two or more items. You can change their values, conditions and limits yourself whenever you want.

### Importing your Amazon catalogue

A tool that takes your Amazon export file and brings your products into Mizzey.

You tell it once which column means what, and it remembers. It shows you exactly what will happen before it changes anything. It handles Arabic text correctly. It converts product variations properly. If you run the same file twice it updates your products, it does not create duplicates.

There is one thing you need to know: the basic Amazon export contains only titles, prices, codes and one image. Full descriptions and variations come from a different report that **Amazon does not enable by default**. You have to ask Amazon Seller Support to activate it. Please start that early.

---

# 4. Your team

Different people get different access. Nobody gets everything by default.

| Role | Can do |
|---|---|
| Warehouse | See orders to prepare, pick, pack, ship, update stock |
| Customer service | See customers and orders, add notes, handle service statuses |
| Marketing | Banners, collections, coupons, home content, marketing reports |
| Accountant | Payments, refunds, invoices, product cost and the financial records available at launch. Advanced profit and customer-value reporting is available later as separate work |
| Admin | Products, orders, customers, offers, users |
| Owner | Everything, including permissions and audit logs |

Sensitive actions are logged: refunds, price changes, stock changes and permission changes, with who did it and when. The log cannot be deleted from the interface.

**A note on marketing agencies.** When you hire a media buyer or SEO specialist, they get the Marketing role only. They cannot install software or edit the site's code. All their tracking goes through one controlled channel. This protects the store from being broken by someone who does not know how it was built. You will receive a short written protocol to give to any agency you hire.

---

# 5. What we recommend, and what we have decided

Your requirements document left three things to us: which payment provider, which shipping carrier,
and what technology to build on. **The first two are recommendations and remain subject to your
approval. The third is our technical decision.** All three become part of the agreed scope when you
sign the Feature Register.

**Payments: we recommend Paymob.** The strongest option in Egypt. Highest security certification,
official integration, widest range of payment methods, and card money reaches you the next business
day. If you would rather use a different provider, tell us before the checkout work starts: we can
accommodate the change without a change fee provided the new provider needs materially equivalent
integration work. If it needs more, we will tell you what the difference costs before anything is
built.

One warning, and it is the most time critical thing in this project. **Getting your merchant account
approved takes longer than building the payment system.** It needs your commercial registration and
tax card. Please start it before anything else. Nothing we build can compensate for an unapproved
account. Note also that approving a provider does not by itself decide what customers can pay with:
the methods live on launch day depend on what you select, what the provider approves for your
account, and what this contract covers.

**Shipping: we recommend Bosta.** The largest technology led courier in Egypt, with an official
integration, bulk dispatch, bulk label printing and cash on delivery collection. Once you approve it,
we need the account in your name, the API credentials and your rate card by governorate. The store is
built so a second carrier can be added later as separate work rather than a rebuild of shipping.

Two things to plan for. Bosta pays you cash on delivery money roughly three to five business days
after delivery, so an order marked Delivered is not money in your account yet. The system tracks
these separately so you always know the difference. And in the Egyptian market, somewhere between 12
and 18 percent of cash on delivery orders are refused at the door. That is a business reality, not a
system fault, but we should discuss whether you want order confirmation by phone or code before
dispatch.

**The technology: we have chosen WordPress with WooCommerce.** This one is our decision rather than a
recommendation, because your requirements document asked us to propose the technology and justify it.
WooCommerce is a mature commerce engine, both providers above publish supported integrations for it,
a multilingual English and Arabic storefront with right to left support is well established territory, and nothing
about it locks you to us: the code goes to a repository you own and the hosting is in your name.

On top of WooCommerce we use **Corex**, and this is our technical decision too. The layers sit in this
order: **WordPress, then WooCommerce, then Corex, then the modules built specifically for Mizzey.**
Corex is our pre-existing reusable framework layer around WooCommerce, covering the parts we build
repeatedly: your operations screen, the storefront controls, the import tool and the integration
layer. **WooCommerce remains the commerce engine underneath.** Corex is not a separate shop system,
not a replacement for WooCommerce and not a subscription.

Corex is maintained independently of Mizzey and its use remains subject to its applicable published
licence. Everything built specifically for Mizzey, the source code, configuration, theme, content and
data, is delivered to you under the project agreement. The precise licence and intellectual property
wording belongs in the Services Agreement rather than in this summary, and should be read against the
published Corex licence.

**Your ERP.** You mentioned yours is nearly finished. It is not connected in this project and no date is promised. What we can say honestly is that the current architecture gives clean integration boundaries and exportable data, which is intended to reduce the effort of connecting an ERP later: every external connection sits behind a clean interface, and your full catalogue can be exported at any time. **That reduces the work; it does not remove it.** The eventual integration may still require implementation or architectural changes, depending on your ERP's interface, its data model and what the business needs it to do. When your ERP is ready, we scope and quote the connection separately.

---

# 6. What is not in the first launch

**Please read this section fully.** It is the one most likely to contain a surprise.

## 6.1 Coming shortly after launch, already paid for in this contract

These are in your contract. They simply are not there on day one.

| Feature | What you have instead at launch |
|---|---|
| One click reorder from order history | Customers can see past orders |
| Notification preferences page | Unsubscribe links in emails |
| Account deletion request page | Handled manually when asked |
| Filter products by rating | All other filters work |
| Product performance report | Basic sales figures available |
| Search terms report | Search itself works fully |
| Customer and conversion figures on the dashboard | Revenue, orders and units are live |
| Scheduled price changes | Change prices manually or in bulk |
| Reserved stock tracking | Stock on hand and available are live |
| Customer lifetime spend figures | Profiles and order history are live |
| Automatic privacy request handling | Handled manually when asked |
| Full stock movement history | Stock levels and adjustments are live |
| Editable notification templates | We change email wording for you on request |

## 6.2 Not included, available later as separate work

If you want any of these, tell us now and we will price them.

**Growth and retention:** Referral programme, which your requirements document places in the growth phase.

**Communication:** WhatsApp and SMS notifications. Abandoned cart recovery. Back in stock alerts.

**Payments:** Instalments and buy now pay later. Kiosk and cash payment points. Cash on delivery confirmation by code.

**Customers:** Facebook and Apple sign in. Customer groups such as VIP or dormant. Wishlist sharing. Price drop alerts.

**Products:** Product comparison. Frequently Bought Together. Automatic rule based collections. Photo and video reviews. Questions and answers. Supplier and purchase order management.

**Offers and gifting:** Buy X get Y. Tiered discounts. Bundle pricing. Offer performance reports. **Gift cards and gift packaging**, which your requirements document places in the growth phase.

**Shipping and returns:** Live shipping rates from the courier. More than one courier. Same day or scheduled delivery. Automated return collection. Return reason analysis.

**Reports:** Profit and margin reporting. Customer lifetime value. Returns analysis.

### Future growth and retention opportunities

Loyalty points, VIP tiers and referrals are the three most likely to be missed from the lists above,
so they are worth a short section of their own. **None of them is in this contract**, and none of them
was ever part of the first release in your own requirements document.

| Opportunity | Where your requirements document places it |
|---|---|
| **Referral programme** | Growth phase, §16.3. Separately scoped and quoted when you want it |
| **Loyalty points** | Advanced phase, §16.4 and §16.5. Separately scoped and quoted when you want it |
| **VIP tiers** | Advanced phase, §16.4 and §16.5. Separately scoped and quoted when you want it |

Being in a later phase does not put them out of reach. **Any of them can be brought forward into an
earlier, separately contracted piece of work by mutual agreement.** The phase records where the
feature started, not a restriction on when you may buy it.

There is also a good commercial reason not to rush loyalty in particular. A loyalty programme needs
customers to be loyal to. Launching one on day one, before you have repeat buyers or order history,
rewards nobody and tells you nothing. Commissioned a few months after launch, the earning rate and the
tier thresholds get set against your real order values instead of guesses.

**Two things to know before you commission loyalty.** Points are money you owe: every point issued is
a future discount against your revenue, so the accounting treatment needs agreeing with your
accountant before any earning rate is set. And referral programmes attract abuse, where one person
opens several accounts and refers themselves, so the fraud controls have to be built with the
programme rather than added afterwards.

**What we have already done about it, at no cost to you.** All three are specified in full in the
Feature Register at Section W, and the data model carries the fields they need from day one. That
means commissioning any of them later is a quotation against a written specification rather than a
fresh round of analysis, and it works against your real order history instead of starting from zero.

**Other:** Arabic admin interface. Manual order creation for phone orders. Google Maps address lookup. Server side ad tracking. Snapchat.

**One thing worth knowing:** we record your **product cost from day one**, even though profit reporting is not built yet. That means when you do want it later, it works against real history instead of starting from zero.

## 6.3 Your longer term vision, not part of this contract

Your requirements document describes a Phase 3 vision beyond the growth phase. **We have not dropped
it and we are not calling it impossible.** It is simply not in this contract, and your own document
never placed it in the first release.

Marketplace selling with external sellers. Mobile applications, whether native or a progressive web
app. AI and personalised recommendations, and a personalised home page. Deep synchronisation with
Amazon or another marketplace. Advanced ERP and accounting synchronisation. Multiple warehouses.
Demand forecasting. A/B testing platform. **Loyalty points and VIP tiers**, which your requirements
document also places in this phase.

There is no price, no date and no commitment attached to any of it. **One thing to be clear about,
because it would be easy to imply otherwise:** we build to sensible, extensible engineering standards
and avoid painting ourselves into corners, but we cannot promise today that any of these could be
added to the launched store without significant rework or, in some cases, a different technology
approach. That is a decision to take properly when the time comes, with the store's real numbers in
front of you.

## 6.3a Genuinely not included

These are excluded from the engagement rather than deferred: a full ERP built from scratch, multiple
currencies and countries, a blog or article section, and direct integration with the tax authority's
e-invoice system, which is handled through your own accounting or ERP system.

## 6.4 One honest clarification about the admin panel

We understand you want the store to feel as complete and organised as Amazon, and we share that goal.

The customer facing store is designed to compete on that level. **The admin panel is a different matter.** It is a professional, capable e commerce administration system built around your actual daily work. It is not a copy of Amazon Seller Central's interface, and it would be dishonest to promise that.

What we have done instead is list, precisely, every operation you will be able to perform. That list is in Section 3 and in full in the attached register. **Before we start building, we will sit with you and demonstrate the actual admin environment**, so you can perform your real daily tasks in it and tell us if something is missing. We would much rather find that out early than after the store is built.

**The admin panel is in English.** The store your customers see is multilingual, English first with Arabic fully supported. The admin is English because mixing an Arabic right to left interface with English custom screens is harder to learn than one consistent language, and because e commerce terminology is documented in English. **Your training and admin manual will be in Arabic.** This is a separate decision from the storefront language and is unchanged.

---

# 6.5 What you receive in writing

Alongside the store itself, you receive a complete written specification of it. These are contractual deliverables, not internal notes, and they belong to you.

The Feature Register lists every requirement individually numbered. The user stories describe every function from the customer's or your team's point of view, each with the conditions that define it as working correctly. There is a technical design, a data model, an event tracking plan, an Arabic administrator manual, and written deployment and recovery procedures.

**These documents describe your business, not our code.** They are written in terms of what the system does rather than how it is built, so nothing in them depends on the platform.

The practical consequence is this. If you ever wanted to rebuild Mizzey on entirely different technology, or hand it to another team, these documents are what you would give them. Every rule, every screen, every status and every calculation is already written down, at a level of detail that would otherwise take months to reconstruct.

Most projects this size do not produce this. When the original developer moves on, the knowledge goes with them and the business finds that nobody can say for certain how their own store works. That will not happen to you.

---

# 7. What we need from you

The store cannot be finished without these. Each one that is late delays everything behind it.

| What | When | If it is late |
|---|---|---|
| **Payment merchant account approved.** Commercial registration and tax card. Paymob if you approve our recommendation, otherwise the provider you choose | **Start first, before anything else** | The store cannot take payment |
| Brand name, logo and visual direction approved | Before design work | All design stops |
| Carrier account, API key and your rate card by governorate, once you have approved a carrier | Before shipping work | Checkout cannot be completed |
| **Amazon sample export file** | Before development | The import tool cannot be finalised |
| **Written confirmation that you own the rights to your product images and descriptions** | Before development | Images will not be imported |
| Your full catalogue export | Before loading products | Launch blocked |
| Written instruction on hosting location | Before setup | Servers cannot be prepared |
| **All storefront content in both languages** for products, categories and pages, English first | Before loading products | The store cannot launch multilingual |
| Legal text: shipping, returns, privacy, terms | Before launch | Launch blocked |
| Product photographs | Before loading products | Launch blocked |
| Feedback on anything we send | Within 5 working days | It is treated as approved |

**Two of these deserve extra attention.**

**Image rights.** You sell products from other brands. Some of the images and descriptions on your Amazon listings were made by those brands, or by Amazon. Moving them to your own store is a different use. We need your written confirmation that you hold the rights, because we cannot verify that for you.

**Content in both languages.** A multilingual store needs two versions of everything a customer reads. We build the multilingual system, the language switcher and the right to left layouts. **The words are yours to supply, in English and in Arabic.** This is the single most common reason multilingual launches are delayed, and it has not changed with the decision to lead in English. Please plan the content and translation alongside the build, not at the end of it.

---

# 8. Still to decide

These are business decisions, not technical ones. We will not guess them on your behalf.

* Welcome discount: what value, what maximum, what expiry, who qualifies
* Free shipping: does "two items" mean two units or two different products, and are there exclusions
* Whether the welcome discount, a coupon and free shipping can all apply to the same order
* Cash on delivery: offered or not, any order value limit, any fee
* Which payment methods at launch: cards only, or cards and wallets
* Return window: how many days, and any product types excluded
* VAT and invoicing requirements for your company
* Your authenticity and warranty policy wording
* Which advertising platforms at launch: Meta only, or Meta with TikTok and Google Shopping
* Hosting location, after taking your own legal advice
* **Whether you approve Paymob as the payment provider**, or would prefer another
* **Whether you approve Bosta as the shipping carrier**, or would prefer another
* **Whether you approve email as the launch notification channel**, with WhatsApp and SMS automation following later as separate work

---

# 9. Before we start

Before development begins, you will receive the technical design, the full site structure and user flows, the interface designs for approval, and a live demonstration of the admin environment.

Nothing gets built until you have seen and approved how it will look and work.

---

# 10. Confirmation

By signing, you confirm you have read this summary, including Section 6 on what is not included, and that the attached Feature Register MS-ANX-2026-001 is the full definition of scope.

| | Client | Developer |
|---|---|---|
| Name | | Mustafa Shaaban |
| Signature | | |
| Date | | |

---

*Questions on anything here are welcome, and much better raised now than later.*
