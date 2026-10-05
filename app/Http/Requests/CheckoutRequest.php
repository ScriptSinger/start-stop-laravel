<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[\d\s()+\-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery' => ['required', Rule::enum(DeliveryMethod::class)],
            'address' => [
                Rule::requiredIf(fn (): bool => (bool) $this->enum('delivery', DeliveryMethod::class)?->needsAddress()),
                'nullable',
                'string',
                'max:500',
            ],
            'payment' => ['required', Rule::enum(PaymentMethod::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Правила, которые зависят от корзины и сочетания полей. Laravel вызывает
     * after() через контейнер, поэтому корзина внедряется параметром.
     *
     * @return list<callable(Validator): void>
     */
    public function after(Cart $cart): array
    {
        return [
            function (Validator $validator) use ($cart): void {
                $digits = preg_replace('/\D/', '', (string) $this->input('phone'));

                if ($this->filled('phone') && (strlen($digits) < 10 || strlen($digits) > 15)) {
                    $validator->errors()->add('phone', 'Проверьте номер телефона: нужно 10–11 цифр.');
                }

                $delivery = $this->enum('delivery', DeliveryMethod::class);
                $payment = $this->enum('payment', PaymentMethod::class);

                if ($delivery === null) {
                    return;
                }

                if ($payment !== null && ! $payment->isAllowedFor($delivery)) {
                    $validator->errors()->add('payment', 'Оплата картой доступна только при самовывозе.');
                }

                if ($delivery !== DeliveryMethod::Pickup && $cart->isPickupOnly()) {
                    $validator->errors()->add('delivery', 'В корзине есть товар, который можно забрать только самовывозом.');
                }
            },
        ];
    }

    /**
     * Сообщения по-русски прямо здесь: язык приложения задаётся в .env,
     * а форма заказа должна быть русской в любом случае.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.max' => 'Имя слишком длинное.',
            'phone.required' => 'Укажите телефон — по нему мы подтвердим заказ.',
            'phone.regex' => 'В телефоне могут быть только цифры, пробелы, скобки, «+» и «-».',
            'phone.max' => 'Проверьте номер телефона.',
            'email.email' => 'Проверьте e-mail.',
            'delivery.required' => 'Выберите способ получения.',
            'delivery.enum' => 'Выберите способ получения.',
            'address.required' => 'Укажите адрес доставки.',
            'address.max' => 'Адрес слишком длинный.',
            'payment.required' => 'Выберите способ оплаты.',
            'payment.enum' => 'Выберите способ оплаты.',
            'comment.max' => 'Комментарий слишком длинный (до 1000 символов).',
        ];
    }

    public function delivery(): DeliveryMethod
    {
        return $this->enum('delivery', DeliveryMethod::class);
    }

    public function payment(): PaymentMethod
    {
        return $this->enum('payment', PaymentMethod::class);
    }

    /**
     * @return array{name: string, phone: string, email: ?string, address: ?string, comment: ?string}
     */
    public function contact(): array
    {
        return [
            'name' => $this->validated('name'),
            'phone' => $this->validated('phone'),
            'email' => $this->validated('email'),
            'address' => $this->validated('address'),
            'comment' => $this->validated('comment'),
        ];
    }
}
