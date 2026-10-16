<x-layouts.public :business="$business" title="Başvurunuz alındı">
    <x-card>
        <x-empty-state icon="check-circle" title="Başvurunuz alındı"
            text="{{ $business->display_name }} başvurunuzu inceleyip size en kısa sürede dönüş yapacak. Başvurunuzun alındığına dair bir e-posta da adresinize gönderildi. Onaylanırsa giriş bilgileriniz size iletilecek." />
    </x-card>
</x-layouts.public>
