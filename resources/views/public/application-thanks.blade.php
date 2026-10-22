<x-layouts.public :business="$business" title="Başvurunuz alındı">
    <x-card>
        @if (session('replied'))
            <x-empty-state icon="check-circle" title="Bilgileriniz iletildi"
                text="{{ $business->display_name }} başvurunuzu kaldığı yerden değerlendirmeye devam edecek. Sonuç e-postayla bildirilecek." />
        @else
            <x-empty-state icon="check-circle" title="Başvurunuz alındı"
                text="{{ $business->display_name }} başvurunuzu inceleyip size en kısa sürede dönüş yapacak. Başvurunuzun alındığına dair bir e-posta da adresinize gönderildi. Onaylanırsa giriş bilgileriniz size iletilecek." />
        @endif
    </x-card>
</x-layouts.public>
