<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Business\DealerController;
use App\Http\Controllers\Controller;
use App\Models\DealerAddress;
use App\Models\DealerRelationship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bayinin sevk adresleri. Adresler seçili toptancıyla olan ilişkiye aittir;
 * toptancı "adres değiştirebilir" iznini kapattıysa bayi yalnızca görüntüler.
 */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $relationship = $this->relationship($request);

        return view('dealer.addresses', ['relationship' => $relationship, 'addresses' => $relationship->addresses]);
    }

    public function store(Request $request): RedirectResponse
    {
        $relationship = $this->editableRelationship($request);
        $data = $request->validate(DealerController::addressRules(), [], DealerController::addressAttributes());

        $address = $relationship->addresses()->create($data + ['is_default' => ! $relationship->addresses()->exists()]);
        if ($request->boolean('is_default')) {
            DealerController::makeOnlyDefault($address);
        }

        return back()->with('status', 'Adres eklendi.');
    }

    public function update(Request $request, DealerAddress $address): RedirectResponse
    {
        $relationship = $this->editableRelationship($request);
        abort_unless($address->dealer_relationship_id === $relationship->id && $address->archived_at === null, 404);

        $address->update($request->validate(DealerController::addressRules(), [], DealerController::addressAttributes()));
        if ($request->boolean('is_default')) {
            DealerController::makeOnlyDefault($address);
        }

        return back()->with('status', 'Adres güncellendi.');
    }

    public function makeDefault(Request $request, DealerAddress $address): RedirectResponse
    {
        $relationship = $this->relationship($request);
        abort_unless($address->dealer_relationship_id === $relationship->id && $address->archived_at === null, 404);

        // Varsayılan adres seçimi, düzenleme izni kapalıyken de yapılabilir (adres içeriği değişmez).
        DealerController::makeOnlyDefault($address);

        return back()->with('status', 'Varsayılan adres güncellendi.');
    }

    public function destroy(Request $request, DealerAddress $address): RedirectResponse
    {
        $relationship = $this->editableRelationship($request);
        abort_unless($address->dealer_relationship_id === $relationship->id, 404);
        DealerController::archive($address);

        return back()->with('status', 'Adres kaldırıldı.');
    }

    private function relationship(Request $request): DealerRelationship
    {
        return DealerRelationship::findOrFail(app(TenantContext::class)->dealerRelationshipId());
    }

    private function editableRelationship(Request $request): DealerRelationship
    {
        $relationship = $this->relationship($request);
        abort_unless($relationship->can_edit_addresses, 403, 'Toptancınız adres değişikliğini kendisi yapmaktadır. Değişiklik için toptancınıza mesaj gönderin.');

        return $relationship;
    }
}
