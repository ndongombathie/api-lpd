<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventaireAjustement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventaireController extends Controller
{
    /**
     * Applique les filtres communs (période, catégorie, fournisseur)
     */
    protected function applyFilters(Request $request)
    {
        $from  = $request->query('from');   // dateDebut côté front
        $to    = $request->query('to');     // dateFin
        $cat   = $request->query('cat');    // catégorie ou "Toutes"
        $fourn = $request->query('fourn');  // fournisseur ou "Tous"

        $q = InventaireAjustement::query();

        if ($from) {
            $q->whereDate('date_comptage', '>=', $from);
        }

        if ($to) {
            $q->whereDate('date_comptage', '<=', $to);
        }

        if ($cat && $cat !== 'Toutes') {
            $q->where('categorie', $cat);
        }

        if ($fourn && $fourn !== 'Tous') {
            $q->where('fournisseur', $fourn);
        }

        return $q;
    }

    /**
     * GET /api/inventaire/kpi
     * (KPI simples, tu peux le garder pour d'autres écrans)
     */
    public function kpi(Request $request)
    {
        $q = $this->applyFilters($request);

        $stockTheo    = (int) $q->sum('stock_theorique');
        $stockReel    = (int) $q->sum('stock_reel');
        $totalEcarts  = (int) $q->sum('ecart');
        $valeurEcarts = (int) $q->sum('valeur_ecart');

        return response()->json([
            'stockTheo'    => $stockTheo,
            'stockReel'    => $stockReel,
            'totalEcarts'  => $totalEcarts,
            'valeurEcarts' => $valeurEcarts,
        ]);
    }

    /**
     * GET /api/inventaire/ajustements
     * Journal détaillé des ajustements (liste simple)
     */
    public function index(Request $request)
    {
        $q = $this->applyFilters($request)
            ->orderBy('date_comptage', 'desc')
            ->orderBy('created_at', 'desc');

        $rows = $q->get();

        $data = $rows->map(function (InventaireAjustement $a) {
            return [
                'id'          => $a->id,
                'date'        => optional($a->date_comptage)->toDateString(),
                'produit'     => $a->produit,
                'categorie'   => $a->categorie,
                'fournisseur' => $a->fournisseur,
                'theorique'   => $a->stock_theorique,
                'reel'        => $a->stock_reel,
                'ecart'       => $a->ecart,
                'valeur'      => $a->valeur_ecart,
                'motif'       => $a->motif,
                'user'        => optional($a->user)->name ?? 'Responsable',
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * ✅ NOUVEAU : GET /api/inventaire/dashboard
     * Retourne :
     * - stats (KPI)
     * - evolutionData (line chart)
     * - categorieData (pie par catégorie)
     * - typeEcartData (pie pertes/gains/sans écart)
     * - lignes (tableau détaillé)
     */
    public function dashboard(Request $request)
    {
        // Filtres supplémentaires
        $typeEcart = $request->query('typeEcart', 'Tous');  // "Tous" | "Perte" | "Gain" | "Sans écart"
        $search    = $request->query('q');                  // recherche texte

        // Base : période, catégorie, fournisseur
        $q = $this->applyFilters($request);

        // Filtre type d’écart
        if ($typeEcart === 'Perte') {
            $q->where('ecart', '<', 0);
        } elseif ($typeEcart === 'Gain') {
            $q->where('ecart', '>', 0);
        } elseif ($typeEcart === 'Sans écart') {
            $q->where('ecart', 0);
        }

        // Recherche plein texte
        if ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('produit', 'LIKE', "%{$search}%")
                    ->orWhere('categorie', 'LIKE', "%{$search}%")
                    ->orWhere('fournisseur', 'LIKE', "%{$search}%")
                    ->orWhere('motif', 'LIKE', "%{$search}%");
            });
        }

        // Lignes finales filtrées
        $lignes = $q->orderBy('date_comptage')
            ->orderBy('created_at')
            ->get();

        // Totaux de base (en "unités", pas en FCFA pour l’instant)
        $totalArticles = $lignes->count();
        $totalTheo     = (int) $lignes->sum('stock_theorique');
        $totalReel     = (int) $lignes->sum('stock_reel');

        // Pertes / gains en quantité
        $pertes = 0;
        $gains  = 0;

        foreach ($lignes as $l) {
            if ($l->ecart < 0) {
                $pertes += abs((int) $l->ecart);
            } elseif ($l->ecart > 0) {
                $gains += (int) $l->ecart;
            }
        }

        $ecartGlobal = $totalReel - $totalTheo;
        $tauxEcart   = $totalTheo ? $ecartGlobal / $totalTheo : 0;

        $stats = [
            'totalArticles' => $totalArticles,
            'totalTheo'     => $totalTheo,
            'totalReel'     => $totalReel,
            'pertes'        => $pertes,
            'gains'         => $gains,
            'ecartGlobal'   => $ecartGlobal,
            'tauxEcart'     => $tauxEcart,
        ];

        // ========= Évolution par date (line chart) =========
        $evolution = [];

        foreach ($lignes as $l) {
            $date = optional($l->date_comptage)->toDateString(); // "YYYY-MM-DD"

            if (!isset($evolution[$date])) {
                $evolution[$date] = [
                    'date'            => $date,
                    'stock_theorique' => 0,
                    'stock_reel'      => 0,
                ];
            }

            $evolution[$date]['stock_theorique'] += (int) $l->stock_theorique;
            $evolution[$date]['stock_reel']      += (int) $l->stock_reel;
        }

        $evolutionData = array_values($evolution);

        // ========= Répartition par catégorie =========
        $parCategorie = [];

        foreach ($lignes as $l) {
            $cat = $l->categorie ?: 'Autres';

            if (!isset($parCategorie[$cat])) {
                $parCategorie[$cat] = [
                    'categorie' => $cat,
                    'pertes'    => 0, // somme des |écarts négatifs|
                    'gains'     => 0, // somme des écarts positifs
                    'ecart_abs' => 0, // somme des |écarts|
                ];
            }

            if ($l->ecart < 0) {
                $parCategorie[$cat]['pertes'] += abs((int) $l->ecart);
            } elseif ($l->ecart > 0) {
                $parCategorie[$cat]['gains'] += (int) $l->ecart;
            }

            $parCategorie[$cat]['ecart_abs'] += abs((int) $l->ecart);
        }

        $categorieData = [];

        foreach ($parCategorie as $row) {
            $categorieData[] = [
                'name'  => $row['categorie'],
                'value' => $row['ecart_abs'],
            ];
        }

        // ========= Répartition pertes / gains / sans écart =========
        $maxStock  = max($totalTheo, $totalReel);
        $sansEcart = max($maxStock - $pertes - $gains, 0);

        $typeEcartData = [
            ['name' => 'Pertes',     'value' => $pertes],
            ['name' => 'Gains',      'value' => $gains],
            ['name' => 'Sans écart', 'value' => $sansEcart],
        ];

        // ========= Lignes détaillées pour le tableau =========
        $lignesPayload = $lignes->map(function (InventaireAjustement $a) {
            return [
                'id'           => $a->id,
                'date'         => optional($a->date_comptage)->toDateString(),
                'produit'      => $a->produit,
                'categorie'    => $a->categorie,
                'fournisseur'  => $a->fournisseur,
                'qteTheorique' => (int) $a->stock_theorique,
                'qteReelle'    => (int) $a->stock_reel,
                'ecart'        => (int) $a->ecart,
                'valeur_ecart' => (int) $a->valeur_ecart,
                'motif'        => $a->motif,
            ];
        })->values();

        return response()->json([
            'filters' => [
                'from'        => $request->query('from'),
                'to'          => $request->query('to'),
                'categorie'   => $request->query('cat'),
                'fournisseur' => $request->query('fourn'),
                'typeEcart'   => $typeEcart,
                'search'      => $search,
            ],
            'stats'          => $stats,
            'evolutionData'  => $evolutionData,
            'categorieData'  => $categorieData,
            'typeEcartData'  => $typeEcartData,
            'lignes'         => $lignesPayload,
        ]);
    }

    /**
     * POST /api/inventaire/ajustements
     * Création d’un nouvel ajustement (comptage)
     */
    public function store(Request $request)
    {
        try {
            // ✅ Le payload correspond à ce que ton front envoie (AjustementModal)
            $validated = $request->validate([
                'date'           => ['required', 'date'],
                'produit'        => ['required', 'string', 'max:255'],
                'categorie'      => ['nullable', 'string', 'max:255'],
                'fournisseur'    => ['nullable', 'string', 'max:255'],
                'stockTheorique' => ['required', 'numeric', 'min:0'],
                'stockReel'      => ['required', 'numeric', 'min:0'],
                'ecart'          => ['required', 'numeric'],   // peut être négatif
                'valeurEcart'    => ['required', 'numeric'],   // en XOF
                'motif'          => ['nullable', 'string', 'max:255'],
            ]);

            $ajustement = InventaireAjustement::create([
                'date_comptage'   => $validated['date'],
                'produit'         => $validated['produit'],
                'categorie'       => $validated['categorie'] ?? null,
                'fournisseur'     => $validated['fournisseur'] ?? null,
                'stock_theorique' => (int) $validated['stockTheorique'],
                'stock_reel'      => (int) $validated['stockReel'],
                'ecart'           => (int) $validated['ecart'],
                'valeur_ecart'    => (int) $validated['valeurEcart'],
                'motif'           => $validated['motif'] ?? null,
                'user_id'         => Auth::id(),
            ]);

            $ajustement->load('user');

            // Format identique à index()
            $payload = [
                'id'          => $ajustement->id,
                'date'        => optional($ajustement->date_comptage)->toDateString(),
                'produit'     => $ajustement->produit,
                'categorie'   => $ajustement->categorie,
                'fournisseur' => $ajustement->fournisseur,
                'theorique'   => $ajustement->stock_theorique,
                'reel'        => $ajustement->stock_reel,
                'ecart'       => $ajustement->ecart,
                'valeur'      => $ajustement->valeur_ecart,
                'motif'       => $ajustement->motif,
                'user'        => optional($ajustement->user)->name ?? 'Responsable',
            ];

            return response()->json($payload, 201);
        } catch (\Throwable $e) {
            \Log::error('Erreur création ajustement inventaire', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur interne lors de la création de l’ajustement.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/inventaire/ecarts-par-categorie
     * Pour le BarChart {categorie, ecart}
     */
    public function ecartsParCategorie(Request $request)
    {
        $q = $this->applyFilters($request);

        $rows = $q->selectRaw('categorie, SUM(ecart) as ecart')
            ->groupBy('categorie')
            ->orderBy('categorie')
            ->get();

        $data = $rows->map(function ($r) {
            return [
                'categorie' => $r->categorie ?? 'N/A',
                'ecart'     => (int) $r->ecart,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * GET /api/inventaire/repartition-ecarts
     * Pour le PieChart [{name, value}]
     */
    public function repartitionEcarts(Request $request)
    {
        $q = $this->applyFilters($request);

        $positifs = (int) $q->clone()->where('ecart', '>', 0)->count();
        $negatifs = (int) $q->clone()->where('ecart', '<', 0)->count();
        $neutres  = (int) $q->clone()->where('ecart', 0)->count();

        $data = [
            [
                'name'  => 'Écarts positifs',
                'value' => $positifs,
            ],
            [
                'name'  => 'Écarts négatifs',
                'value' => $negatifs,
            ],
        ];

        if ($neutres > 0) {
            $data[] = [
                'name'  => 'Sans écart',
                'value' => $neutres,
            ];
        }

        return response()->json($data);
    }

    /**
     * GET /api/inventaire/evolution-valeur-ecarts
     * Pour l’AreaChart [{date, valeur}]
     */
    public function evolutionValeurEcarts(Request $request)
    {
        $q = $this->applyFilters($request);

        $rows = $q->selectRaw('date_comptage as date, SUM(valeur_ecart) as valeur')
            ->groupBy('date_comptage')
            ->orderBy('date_comptage')
            ->get();

        $data = $rows->map(function ($r) {
            return [
                'date'   => $r->date,
                'valeur' => (int) $r->valeur,
            ];
        })->values();

        return response()->json($data);
    }
}
