from collections.abc import Sequence
from math import isfinite


def forecast_brown(
    actuals: Sequence[int | float],
    alpha: float,
) -> float:
    """
    Menghitung prediksi satu periode berikutnya menggunakan
    Double Exponential Smoothing Brown dengan alpha tetap.

    State smoothing dihitung ulang dari seluruh deret aktual
    setiap kali fungsi dijalankan.
    """
    if len(actuals) < 1:
        raise ValueError(
            "Deret DES minimal terdiri dari satu periode."
        )

    if not isfinite(alpha) or not 0 < alpha < 1:
        raise ValueError(
            "Alpha DES harus berada di antara 0 dan 1."
        )

    normalized_actuals: list[float] = []

    for actual in actuals:
        value = float(actual)

        if not isfinite(value) or value < 0:
            raise ValueError(
                "Nilai penjualan aktual harus berupa angka "
                "hingga dan tidak boleh negatif."
            )

        normalized_actuals.append(value)

    first_smoothing = normalized_actuals[0]
    second_smoothing = normalized_actuals[0]
    level = normalized_actuals[0]
    trend = 0.0

    for actual in normalized_actuals[1:]:
        first_smoothing = (
            alpha * actual
            + (1 - alpha) * first_smoothing
        )

        second_smoothing = (
            alpha * first_smoothing
            + (1 - alpha) * second_smoothing
        )

        level = (
            2 * first_smoothing
            - second_smoothing
        )

        trend = (
            alpha
            / (1 - alpha)
            * (first_smoothing - second_smoothing)
        )

    prediction = level + trend

    if not isfinite(prediction):
        raise ValueError(
            "Perhitungan DES menghasilkan nilai yang tidak valid."
        )

    return max(prediction, 0.0)
