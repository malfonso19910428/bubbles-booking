<?php
/**
 * Bubbles Custom Pricing
 * - BB_Vehicle_Classifier: detecta clase del vehículo
 * - BB_Pricing: calcula precios de 3 paquetes según la clase y reglas
 *
 * Uso típico:
 *   $rules = include BB_PLUGIN_DIR . 'includes/config/bb-pricing-rules.php';
 *   $pricing = new BB_Pricing($rules);
 *   $packages = $pricing->quote(['year'=>2020,'make'=>'Toyota','model'=>'RAV4']);
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('BB_Vehicle_Classifier')) {
class BB_Vehicle_Classifier {

    /** Retorna SIEMPRE una clase válida. */
    public static function detect_class(array $vehicle, array $rules = []): string {
        // 1) Overrides por modelo exacto/contiene
        $make  = strtolower(trim((string)($vehicle['make']  ?? '')));
        $model = trim((string)($vehicle['model'] ?? ''));
        foreach (($rules['model_overrides'] ?? []) as $needle => $class) {
            if ($needle !== '' && stripos($model, $needle) !== false) {
                return self::sanitize_class($class, $rules);
            }
        }

        // 2) Heurística por keywords del modelo
        $m = strtolower($model);
        if (self::contains_any($m, ['sprinter', 'transit', 'promaster', 'econoline', 'savanna'])) {
            return self::sanitize_class('van', $rules);
        }
        if (self::contains_any($m, ['pickup', 'pick-up', 'f-150', 'f150', 'silverado', 'sierra', 'tundra', 'ram'])) {
            return self::sanitize_class('truck', $rules);
        }
        if (self::contains_any($m, ['suv', 'crossover', 'rav4', 'cr-v', 'crv', 'highlander', 'tahoe', 'suburban', 'pilot', 'escape', 'explorer', 'rogue'])) {
            return self::sanitize_class('suv', $rules);
        }
        if (self::contains_any($m, ['escalade', 'range rover', 'g-class', 'q8', 'x7', 'gls', 'bentley', 'maserati'])) {
            return self::sanitize_class('luxury', $rules);
        }

        // 3) Heurística por make (marcas premium)
        if (self::contains_any($make, ['mercedes-benz','bmw','audi','lexus','porsche','land rover','jaguar','infiniti','acura'])) {
            return self::sanitize_class('luxury', $rules);
        }

        // 4) Fallback seguro
        return self::sanitize_class('midsize', $rules);
    }

    private static function contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $n) {
            if ($n !== '' && strpos($haystack, strtolower($n)) !== false) return true;
        }
        return false;
    }

    private static function sanitize_class(string $class, array $rules): string {
        $available = array_keys($rules['base'] ?? []);
        if (in_array($class, $available, true)) return $class;
        // si la clase no existe en reglas, usa 'midsize' o la primera disponible
        if (in_array('midsize', $available, true)) return 'midsize';
        return $available[0] ?? 'midsize';
    }
}}
/* end class BB_Vehicle_Classifier */


if (!class_exists('BB_Pricing')) {
class BB_Pricing {

    private $rules;

    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * Calcula los precios para basic/standard/premium.
     * @param array $vehicle ['year'=>int,'make'=>string,'model'=>string]
     * @return array [['id','label','price','breakdown','class_detected'], ...]
     */
    public function quote(array $vehicle): array {
        $class = BB_Vehicle_Classifier::detect_class($vehicle, $this->rules);

        $baseByClass = $this->rules['base'][$class] ?? null;
        if (!$baseByClass) {
            // fallback de seguridad
            $class = 'midsize';
            $baseByClass = $this->rules['base'][$class] ?? ['basic'=>100,'standard'=>150,'premium'=>200];
        }

        $out = [];
        foreach ($baseByClass as $pkgId => $basePrice) {
            $label = $this->rules['packages_labels'][$pkgId] ?? ucfirst($pkgId);
            [$final, $breakdown] = $this->apply_adjustments((float)$basePrice, $pkgId, $class, $vehicle);
            $out[] = [
                'id'             => $pkgId,
                'label'          => $label,
                'price'          => $final,
                'breakdown'      => $breakdown,
                'class_detected' => $class,
            ];
        }
        // Orden por jerarquía conocida
        usort($out, function($a,$b){
            $ord = ['basic'=>1,'standard'=>2,'premium'=>3];
            return ($ord[$a['id']] ?? 99) <=> ($ord[$b['id']] ?? 99);
        });
        return $out;
    }

    /** Aplica brand multipliers + year adjustments. */
    private function apply_adjustments(float $base, string $pkgId, string $class, array $vehicle): array {
        $price = $base;
        $break = [
            ['label' => "Base ({$class} · {$pkgId})", 'amount' => $base],
        ];

        // 1) Multiplicador por marca
        $make = strtolower(trim((string)($vehicle['make'] ?? '')));
        if ($make !== '' && !empty($this->rules['brand_multipliers'])) {
            foreach ($this->rules['brand_multipliers'] as $brand => $mul) {
                if ($brand !== '' && strpos($make, $brand) !== false) {
                    $delta = $price * ((float)$mul - 1.0);
                    $price *= (float)$mul;
                    $break[] = ['label' => "Brand multiplier (" . ucwords($brand) . " ×" . rtrim(rtrim(number_format((float)$mul, 2, '.', ''), '0'), '.') . ")", 'amount' => round($delta, 2)];
                    break; // aplica el primero que coincida
                }
            }
        }

        // 2) Ajustes por año
        $year = (int)($vehicle['year'] ?? 0);
        if ($year && !empty($this->rules['year_adjustments'])) {
            // Sumas
            if (!empty($this->rules['year_adjustments']['add'])) {
                foreach ($this->rules['year_adjustments']['add'] as $expr => $addVal) {
                    if ($this->year_matches($year, $expr)) {
                        $price += (float)$addVal;
                        $break[] = ['label' => "Year adj ({$expr})", 'amount' => (float)$addVal];
                    }
                }
            }
            // Multiplicadores
            if (!empty($this->rules['year_adjustments']['mul'])) {
                foreach ($this->rules['year_adjustments']['mul'] as $expr => $mulVal) {
                    if ($this->year_matches($year, $expr)) {
                        $before = $price;
                        $price *= (float)$mulVal;
                        $break[] = ['label' => "Year multiplier ({$expr} ×" . rtrim(rtrim(number_format((float)$mulVal, 2, '.', ''), '0'), '.') . ")", 'amount' => round($price - $before, 2)];
                    }
                }
            }
        }

        // 3) Redondeo final bonito
        $final = $this->round_price($price);
        $break[] = ['label' => 'Rounded total', 'amount' => $final - $price];

        return [$final, $break];
    }

    /** Matchea expresiones simples de año: ">=2023", "<=2007", "2008-2014". */
    private function year_matches(int $year, string $expr): bool {
        $expr = trim($expr);
        if (preg_match('/^\>=\s*(\d{4})$/', $expr, $m)) {
            return $year >= (int)$m[1];
        }
        if (preg_match('/^\<=\s*(\d{4})$/', $expr, $m)) {
            return $year <= (int)$m[1];
        }
        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $expr, $m)) {
            $a = (int)$m[1]; $b = (int)$m[2];
            return ($year >= $a && $year <= $b);
        }
        return false;
        }

    /** Redondea a .99 (opcional: cámbialo a tu gusto). */
    private function round_price(float $n): float {
        // Redondeo a entero y restar 0.01 para terminar en .99
        $rounded = ceil($n);       // hacia arriba
        $nice    = $rounded - 0.01;
        // Evita 0.99 en casos muy bajos
        if ($nice < 19.99) $nice = 19.99;
        return (float) number_format($nice, 2, '.', '');
    }
}}
/* end class BB_Pricing */


/**
 * Helper simple por si quieres llamar rápido desde otras partes:
 *   $quote = bb_custom_price_quote($vehicle);
 */
if (!function_exists('bb_custom_price_quote')) {
function bb_custom_price_quote(array $vehicle): array {
    $rulesPath = BB_PLUGIN_DIR . 'includes/config/bb-pricing-rules.php';
    $rules = file_exists($rulesPath) ? include $rulesPath : [];
    $pricing = new BB_Pricing($rules);
    return $pricing->quote($vehicle);
}}
