<?php

declare(strict_types=1);

function validation_name(string $value, string $label, int $maxLength = 100): ?string
{
    if ($value === '') {
        return "El {$label} es obligatorio.";
    }
    if (strlen($value) > $maxLength) {
        return "El {$label} no puede superar {$maxLength} caracteres.";
    }
    if (!preg_match("/^[\\p{L}]+(?:[ '-][\\p{L}]+)*$/u", $value)) {
        return "El {$label} solo puede contener letras, espacios y guiones.";
    }

    return null;
}

function validation_text(string $value, string $label, int $maxLength): ?string
{
    if ($value === '') {
        return "El {$label} es obligatorio.";
    }
    if (strlen($value) > $maxLength) {
        return "El {$label} no puede superar {$maxLength} caracteres.";
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return "El {$label} contiene caracteres no validos.";
    }

    return null;
}

/** Normaliza nombres y títulos, conservando partículas internas en minúscula. */
function normalize_name(string $value): string
{
    $value = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    $words = explode(' ', mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'));
    $particles = ['de', 'del', 'la', 'las', 'los', 'y'];

    foreach ($words as $index => $word) {
        $lower = mb_strtolower($word, 'UTF-8');
        if ($index > 0 && in_array($lower, $particles, true)) {
            $words[$index] = $lower;
        }
    }

    return implode(' ', $words);
}

function validation_label(string $value, string $label, int $maxLength = 120, int $minLength = 3): ?string
{
    if ($value === '') {
        return "El {$label} es obligatorio.";
    }
    if (mb_strlen($value) < $minLength) {
        return "El {$label} debe tener al menos {$minLength} caracteres.";
    }
    if (mb_strlen($value) > $maxLength) {
        return "El {$label} no puede superar {$maxLength} caracteres.";
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return "El {$label} contiene caracteres no validos.";
    }
    // Debe contener al menos una letra: evita nombres formados solo por numeros o simbolos.
    if (!preg_match('/\p{L}/u', $value)) {
        return "El {$label} debe contener al menos una letra.";
    }
    // Solo letras, numeros, espacios y puntuacion basica de nombres academicos.
    if (!preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} .,'()\\/&+-]*$/u", $value)) {
        return "El {$label} solo puede contener letras, numeros, espacios y . , ' ( ) / & + -";
    }

    return null;
}

/**
 * Carnet de Identidad boliviano: numero obligatorio (4-10 digitos) con
 * complemento/extension OPCIONAL (p. ej. "1234567", "1234567 LP", "12345678-1K").
 */
function validation_ci(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'El carnet de identidad es obligatorio.';
    }
    if (!preg_match('/^[0-9]{4,10}(?:[ -]?[0-9A-Za-z]{1,3}){0,2}$/', $value)) {
        return 'El carnet de identidad no es valido (numero, con complemento o extension opcional).';
    }

    return null;
}

function validation_username(string $value): ?string
{
    if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $value)) {
        return 'El usuario debe tener entre 4 y 50 caracteres y solo usar letras, numeros, punto, guion o guion bajo.';
    }

    return null;
}

function validation_phone(string $value): ?string
{
    if ($value !== '' && !preg_match('/^[0-9]{7,20}$/', $value)) {
        return 'El telefono debe contener entre 7 y 20 numeros.';
    }

    return null;
}

function validation_code(string $value, string $label, int $maxLength): ?string
{
    if ($value !== '' && !preg_match('/^[A-Za-z0-9-]{1,' . $maxLength . '}$/', $value)) {
        return "El {$label} solo puede contener letras, numeros y guiones.";
    }

    return null;
}

function validation_time(string $value, string $label): ?string
{
    $time = DateTime::createFromFormat('!H:i', $value);
    if (!$time || $time->format('H:i') !== $value) {
        return "La {$label} no es valida.";
    }

    return null;
}
