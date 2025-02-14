<?php

namespace Azuriom\Plugin\Shop\Models;

use Azuriom\Azuriom;
use Azuriom\Models\Traits\HasTablePrefix;
use Azuriom\Models\Traits\HasUser;
use Azuriom\Models\User;
use Azuriom\Plugin\Shop\Events\PaymentPaid;
use Azuriom\Plugin\Shop\Notifications\PaymentPaid as PaymentPaidNotification;
use Azuriom\Support\Discord\DiscordWebhook;
use Azuriom\Support\Discord\Embed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property float $price
 * @property string $currency
 * @property string $status
 * @property string $gateway_type
 * @property string $transaction_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Azuriom\Models\User $user
 * @property \Illuminate\Support\Collection|\Azuriom\Plugin\Shop\Models\PaymentItem[] $items
 * @property \Illuminate\Support\Collection|\Azuriom\Plugin\Shop\Models\Coupon[] $coupons
 *
 * @method static \Illuminate\Database\Eloquent\Builder completed()
 * @method static \Illuminate\Database\Eloquent\Builder pending()
 * @method static \Illuminate\Database\Eloquent\Builder notPending()
 * @method static \Illuminate\Database\Eloquent\Builder withSiteMoney()
 * @method static \Illuminate\Database\Eloquent\Builder withRealMoney()
 */
class Payment extends Model
{
    use HasTablePrefix;
    use HasUser;

    /**
     * The table prefix associated with the model.
     *
     * @var string
     */
    protected $prefix = 'shop_';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'price', 'currency', 'status', 'gateway_type', 'transaction_id', 'user_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'float',
    ];

    /**
     * Get the category of this package.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items purchased in this payment.
     */
    public function items()
    {
        return $this->hasMany(PaymentItem::class);
    }

    /**
     * Get the coupons used in this payment.
     */
    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'shop_coupon_payment');
    }

    public function getTypeName()
    {
        return Gateway::getNameByType($this->gateway_type);
    }

    public function deliver()
    {
        $this->update(['status' => 'completed']);

        foreach ($this->items as $item) {
            $item->deliver();
        }

        if ($this->gateway_type === 'azuriom') {
            return;
        }

        event(new PaymentPaid($this));

        if (($webhookUrl = setting('shop.webhook')) !== null) {
            rescue(fn () => $this->createDiscordWebhook()->send($webhookUrl));
        }

        rescue(fn () => $this->user->notify(new PaymentPaidNotification($this)));
    }

    public function createDiscordWebhook()
    {
        $embed = Embed::create()
            ->title(trans('shop::messages.payment.webhook'))
            ->author($this->user->name, null, $this->user->getAvatar())
            ->addField(trans('shop::messages.fields.price'), $this->price.' '.currency_display($this->currency))
            ->addField(trans('messages.fields.type'), $this->getTypeName())
            ->addField(trans('shop::messages.fields.payment_id'), $this->transaction_id ?? trans('messages.none'))
            ->url(route('shop.admin.payments.show', $this))
            ->color('#004de6')
            ->footer('Azuriom v'.Azuriom::version())
            ->timestamp(now());

        return DiscordWebhook::create()->addEmbed($embed);
    }

    public function statusColor()
    {
        return match ($this->status) {
            'pending', 'expired' => 'warning',
            'chargeback', 'error' => 'danger',
            'completed' => 'success',
            default => 'secondary',
        };
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function scopeCompleted(Builder $query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNotPending(Builder $query)
    {
        return $query->where('status', '!=', 'pending');
    }

    public function scopeWithSiteMoney(Builder $query)
    {
        return $query->where('gateway_type', '=', 'azuriom');
    }

    public function scopeWithRealMoney(Builder $query)
    {
        return $query->where('gateway_type', '!=', 'azuriom');
    }

    public function getPaymentIdAttribute()
    {
        return $this->transaction_id;
    }

    public function setPaymentIdAttribute($value)
    {
        $this->transaction_id = $value;
    }
}
