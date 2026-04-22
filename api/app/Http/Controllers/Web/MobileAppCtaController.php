<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page "Leopardo RH est mobile-first" (APV v2, L.02).
 *
 * A partir de la v2 de l'architecture produit, un employe simple
 * ("role=employee") n'a PAS d'interface web propre : l'app mobile Flutter
 * est la source de verite pour le pointage, les heures supplementaires,
 * le gain estime, etc.
 *
 * Cette page sert de porte de sortie lorsqu'un employe authentifie
 * arrive sur /mobile (via une redirection depuis /login ou un ancien
 * lien /me) : elle lui rappelle de telecharger l'app et lui propose
 * de se deconnecter.
 *
 * Elle n'est volontairement PAS geographique : les deep links concrets
 * Play Store / App Store seront ajoutes quand les builds seront publies.
 */
class MobileAppCtaController extends Controller
{
    public function index(Request $request): View
    {
        return view('mobile.cta', [
            'employee' => $request->user(),
        ]);
    }
}
