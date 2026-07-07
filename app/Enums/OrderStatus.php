<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingReview = 'pending_review';
    case Modified = 'modified';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case CancelledByRep = 'cancelled_by_rep';
    case CancelledByInvoicer = 'cancelled_by_invoicer';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'بانتظار المراجعة',
            self::Modified => 'تم التعديل',
            self::PartiallyFulfilled => 'مسلّمة جزئياً',
            self::Approved => 'معتمدة',
            self::Rejected => 'مرفوضة',
            self::CancelledByRep => 'ملغاة (مندوب)',
            self::CancelledByInvoicer => 'ملغاة (مفوتر)',
            self::Archived => 'مأرشفة',
        };
    }

    /** @return list<OrderStatus> */
    public static function editableByInvoicer(): array
    {
        return [
            self::PendingReview,
            self::Modified,
            self::PartiallyFulfilled,
        ];
    }

    /** @return list<OrderStatus> */
    public static function cancellableByInvoicer(): array
    {
        return [self::PendingReview, self::Modified];
    }

    /** @return list<OrderStatus> */
    public static function rejectable(): array
    {
        return [self::PendingReview, self::Modified];
    }
}
