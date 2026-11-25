# جریان حسابداری فاکتور

```
create (draft, no number)
   ↓  totals from items; tax from tax_rate_bp snapshot
   ↓
issue (posted)
   ├─ assigns INV-1405-NNNN
   ├─ freezes subtotal / discount / tax / total
   └─ posts: Dr AR · Cr Revenue · Cr VAT
   ↓
record payment
   ├─ posts: Dr Cash · Cr AR
   ├─ allocation row (payment ↔ invoice)
   └─ syncPaymentState → unpaid | partial | paid
   ↓
void (only if paid_amount = 0)
   └─ reversing entry; invoice.status = void
```

## چرا شماره در زمان صدور است

پیش‌نویس شماره ندارد. اگر شماره در زمان ایجاد تعیین می‌شد، هر پیش‌نویس رهاشده یک شکاف در توالی قانونی می‌گذاشت و حسابرس باید هر شکاف را توضیح می‌داد.

## چرا مبالغ در زمان صدور قفل می‌شوند

پس از صدور، `total` همان چیزی است که مشتری دریافت کرده. بازمحاسبهٔ آن یعنی امکان تغییر مبلغ یک سند تحویل‌شده با ویرایش ردیف‌ها.

## تخفیف

تخفیف از **درآمد** کسر می‌شود، نه اینکه هزینه ثبت شود. ثبت تخفیف به‌عنوان هزینه، درآمد ناخالص و هزینه را هر دو باد می‌کند و حاشیهٔ سود را کم‌نمایی می‌کند.

## مالیات

مالیات بدهی است، نه درآمد. مخلوط کردنشان سود را کاذب بالا می‌برد. نرخ در لحظهٔ صدور روی فاکتور قفل می‌شود (`tax_rate_bp`).
