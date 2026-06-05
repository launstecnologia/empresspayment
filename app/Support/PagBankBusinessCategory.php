<?php

namespace App\Support;

class PagBankBusinessCategory
{
    public const FALLBACK = 'OTHER_SERVICES';

    /**
     * Palavras-chave normalizadas => categoria PagBank.
     *
     * @var array<string, string>
     */
    private const MAPEAMENTO = [
        'alimentacao' => 'FOOD_SERVICE',
        'restaurante' => 'FOOD_SERVICE',
        'food service' => 'FOOD_SERVICE',
        'comercio varejista alimentos' => 'FOOD_RETAIL',
        'food retail' => 'FOOD_RETAIL',
        'padaria' => 'BAKERY',
        'bakery' => 'BAKERY',
        'supermercado' => 'SUPERMARKET',
        'supermarket' => 'SUPERMARKET',
        'saude e beleza' => 'HEALTH_AND_BEAUTY_SERVICE',
        'estetica' => 'HEALTH_AND_BEAUTY_SERVICE',
        'health and beauty' => 'HEALTH_AND_BEAUTY_SERVICE',
        'cabelereiro' => 'BEAUTY_AND_BARBER',
        'barbeiro' => 'BEAUTY_AND_BARBER',
        'barbearia' => 'BEAUTY_AND_BARBER',
        'salao' => 'BEAUTY_AND_BARBER',
        'vestuario' => 'CLOTHING_AND_ACCESSORIES',
        'roupas' => 'CLOTHING_AND_ACCESSORIES',
        'clothing' => 'CLOTHING_AND_ACCESSORIES',
        'eletronicos' => 'HOUSEHOLD_APPLIANCE',
        'eletrodomesticos' => 'HOUSEHOLD_APPLIANCE',
        'utensilios domesticos' => 'HOUSE_HOLD',
        'house hold' => 'HOUSE_HOLD',
        'pet shop' => 'PET_SUPPLIES',
        'pet' => 'PET_SUPPLIES',
        'educacao' => 'EDUCATION',
        'education' => 'EDUCATION',
        'automoveis e acessorios' => 'VEHICLE_AND_PARTS',
        'automoveis' => 'VEHICLE_AND_PARTS',
        'mecanica' => 'VEHICLE_SERVICES',
        'borracheiro' => 'VEHICLE_SERVICES',
        'servicos profissionais' => 'PROFESSIONAL_SERVICE',
        'profissional' => 'PROFESSIONAL_SERVICE',
        'medicos' => 'MEDICAL_SERVICE',
        'saude' => 'MEDICAL_SERVICE',
        'medical' => 'MEDICAL_SERVICE',
        'dentista' => 'DENTISTRY',
        'odontologia' => 'DENTISTRY',
        'personal trainer' => 'PERSONAL_TRAINER',
        'fotografia' => 'PHOTOGRAPHY_AND_VIDEO',
        'video' => 'PHOTOGRAPHY_AND_VIDEO',
        'joias e relogios' => 'JEWELRY_AND_WATCH',
        'joias' => 'JEWELRY_AND_WATCH',
        'hospedagem' => 'LODGING',
        'turismo' => 'LODGING',
        'advocacia' => 'LEGAL_SERVICE',
        'juridico' => 'LEGAL_SERVICE',
        'imobiliaria' => 'REAL_ESTATE_AGENT',
        'entretenimento' => 'ENTERTAINMENT',
        'lazer' => 'ENTERTAINMENT',
        'tatuadores' => 'TATTOOIST',
        'tatuagem' => 'TATTOOIST',
        'taxi' => 'TRANSPORTATION_SERVICE',
        'transporte' => 'TRANSPORTATION_SERVICE',
        'marcenaria' => 'OTHER_SERVICES',
        'serralheria' => 'OTHER_SERVICES',
        'reparos' => 'REPAIR_SHOPS',
        'chaveiro' => 'KEY_CHAIN',
        'floricultura' => 'FLORIST',
        'jardinagem' => 'FLORIST',
        'comercio de bebidas' => 'PACKAGE_STORE',
        'bebidas' => 'PACKAGE_STORE',
        'outros' => 'OTHER_SERVICES',
    ];

    public static function resolver(?string $segmento): string
    {
        if (blank($segmento)) {
            return self::FALLBACK;
        }

        $normalizado = self::normalizar($segmento);

        if (isset(self::MAPEAMENTO[$normalizado])) {
            return self::MAPEAMENTO[$normalizado];
        }

        foreach (self::MAPEAMENTO as $chave => $categoria) {
            if (str_contains($normalizado, $chave) || str_contains($chave, $normalizado)) {
                return $categoria;
            }
        }

        return self::FALLBACK;
    }

    public static function mapeado(?string $segmento): bool
    {
        return self::resolver($segmento) !== self::FALLBACK || self::normalizar($segmento ?? '') === 'outros';
    }

    public static function normalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;
        $valor = preg_replace('/[^a-z0-9\s]/', ' ', $valor) ?? $valor;

        return preg_replace('/\s+/', ' ', trim($valor)) ?? '';
    }
}
