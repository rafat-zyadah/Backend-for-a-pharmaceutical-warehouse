<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case InvoiceDebit = 'invoice_debit';
    case CollectionCredit = 'collection_credit';
    case ReturnCredit = 'return_credit';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceDebit => 'فاتورة (مدين)',
            self::CollectionCredit => 'تحصيل (دائن)',
            self::ReturnCredit => 'مرتجع (دائن)',
            self::Adjustment => 'تعديل مالي',
        };
    }
}
