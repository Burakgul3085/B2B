<x-layouts.public :business="$business" title="Başvurunuz alındı">
    <x-card>
        <x-empty-state icon="check-circle" title="Başvurunuz alındı"
            text="{{ $business->display_name }} başvurunuzu inceleyip size e-posta ile dönüş yapacak. Onaylanırsa hesabınızı etkinleştirme bağlantısı iletilecek; parolanızı kendiniz belirleyeceksiniz." />
    </x-card>
</x-layouts.public>
