<?php

namespace App\Domain\Store\Reviews;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductQuestion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ürün Soru & Cevap. Ziyaretçi üye olmadan ad, soyad ve e-postasıyla soru sorar; soru vitrinde görünmez. Mağaza
 * cevapladığında soru ve cevap birlikte yayımlanır, cevap soranın e-postasına gönderilir. E-posta vitrinde gösterilmez.
 */
class QuestionService
{
    public function __construct(private AuditLogger $audit, private AdminNotifier $notifier) {}

    /** @throws ValidationException */
    public function ask(StoreProduct $product, array $input, ?StoreCustomer $customer, ?string $ip): StoreProductQuestion
    {
        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'question' => ['required', 'string', 'min:10', 'max:1000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Cevabın e-postanıza gönderilmesini onaylayın.'],
            ['first_name' => 'ad', 'last_name' => 'soyad', 'email' => 'e-posta', 'question' => 'soru'])->validate();

        $email = mb_strtolower(trim($data['email']));
        if (StoreProductQuestion::where('email', $email)->where('status', 'pending')->count() >= StoreProductQuestion::MAX_OPEN_PER_EMAIL) {
            throw ValidationException::withMessages(['question' => 'Cevap bekleyen sorularınız var; cevapladıktan sonra yeni soru sorabilirsiniz.']);
        }

        $question = StoreProductQuestion::create([
            'product_id' => $product->id,
            'customer_id' => $customer?->id,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => $email,
            'question' => trim($data['question']),
            'ip' => $ip,
        ]);
        $this->notifier->questionAsked($question, $product);

        return $question;
    }

    /**
     * Cevaplar (veya cevabı düzenler): soru ve cevap birlikte yayımlanır; ilk cevapta soranın e-postasına gönderilir.
     *
     * @throws ValidationException
     */
    public function answer(StoreProductQuestion $question, array $input, int $adminId): void
    {
        $data = Validator::make($input, [
            'answer' => ['required', 'string', 'min:2', 'max:2000'],
            'question' => ['nullable', 'string', 'min:10', 'max:1000'],
        ], [], ['answer' => 'cevap', 'question' => 'soru'])->validate();

        $first = $question->answered_at === null;
        $old = $question->only(['status', 'answer', 'question']);
        $question->update([
            'answer' => trim($data['answer']),
            // Yazım hatası düzeltmesi: soru metni yayımlanmadan önce sadeleştirilebilir.
            'question' => filled($data['question'] ?? null) ? trim($data['question']) : $question->question,
            'status' => 'answered',
            'answered_at' => $question->answered_at ?? now(),
            'answered_by' => $adminId,
        ]);
        $this->audit->log('store.question_answered', $question, $old, $question->only(['status', 'answer', 'question']));

        if ($first) {
            $this->mailAnswer($question);
        }
    }

    public function setHidden(StoreProductQuestion $question, bool $hidden): void
    {
        $old = $question->status;
        $question->update(['status' => $hidden ? 'hidden' : ($question->answer ? 'answered' : 'pending')]);
        $this->audit->log($hidden ? 'store.question_hidden' : 'store.question_shown', $question, ['status' => $old], ['status' => $question->status]);
    }

    public function delete(StoreProductQuestion $question): void
    {
        $this->audit->log('store.question_deleted', $question, $question->only(['product_id', 'status']));
        $question->delete();
    }

    private function mailAnswer(StoreProductQuestion $question): void
    {
        $product = $question->product;
        $url = route('store.product', $product->slug).'#sorular';
        $content = [
            'eyebrow' => 'Soru & Cevap',
            'title' => 'Sorunuz cevaplandı',
            'intro' => "Merhaba {$question->first_name}, \"{$product->name}\" hakkında sorduğunuz soruyu cevapladık. Soru ve cevap, diğer ziyaretçilerimize de yol göstermesi için ürün sayfasında yayımlandı (adınız ".$question->displayName().' olarak görünür).',
            'rows' => [['Sorunuz', $question->question], ['Cevabımız', (string) $question->answer]],
            'action_url' => $url,
            'action_label' => 'Ürünü görüntüle',
            'outro' => 'Başka bir sorunuz olursa ürün sayfasından yeniden sorabilirsiniz.',
        ];
        $id = $question->id;
        dispatch(function () use ($question, $content, $id) {
            if (app(StoreMailer::class)->send($question->email, 'Sorunuz cevaplandı — '.$question->product->name, $content)) {
                StoreProductQuestion::whereKey($id)->update(['answer_mailed_at' => now()]);
            }
        })->afterResponse();
    }
}
