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
