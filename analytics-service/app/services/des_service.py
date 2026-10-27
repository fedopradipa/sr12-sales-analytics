from app.domain.brown_des import forecast_brown
from app.schemas.des_schema import (
    DesBatchRequest,
    DesCalibrationRequest,
)


class DesService:
    def forecast_batch(
        self,
        payload: DesBatchRequest,
    ) -> dict[str, list[dict[str, int | float]]]:
        results: list[dict[str, int | float]] = []

        for product in payload.products:
            prediction = forecast_brown(
                actuals=[
                    item.aktual
                    for item in product.series
                ],
                alpha=float(product.alpha),
            )

            results.append({
                "produk_id": product.produk_id,
                "prediksi": round(prediction, 4),
            })

        return {"results": results}

    def calibrate_batch(
        self,
        payload: DesCalibrationRequest,
    ) -> dict[str, list[dict[str, int | float]]]:
        results: list[dict[str, int | float]] = []

        for product in payload.products:
            actuals = [
                int(item.aktual)
                for item in product.series
            ]

            test_count = int(payload.test_months)
            train = actuals[:-test_count]
            test = actuals[-test_count:]

            # Periode pertama menjadi inisialisasi Brown.
            # Mulai periode kedua, forecast sudah dapat dievaluasi.
            if len(train) < 2:
                raise ValueError(
                    "Data train DES minimal dua bulan."
                )

            train_evaluation = train[1:]
            train_denominator = float(
                sum(train_evaluation)
            )
            test_denominator = float(sum(test))

            if train_denominator <= 0:
                raise ValueError(
                    "Total aktual data train yang dievaluasi "
                    "harus lebih dari nol."
                )

            if test_denominator <= 0:
                raise ValueError(
                    "Total aktual pada data test harus "
                    "lebih dari nol."
                )

            best_alpha: float | None = None
            best_wape_train: float | None = None
            best_mae_train: float | None = None

            for alpha_step in range(1, 100):
                alpha = alpha_step / 100.0

                train_history = [train[0]]
                absolute_error_train = 0.0

                for actual in train_evaluation:
                    prediction = forecast_brown(
                        actuals=train_history,
                        alpha=alpha,
                    )

                    absolute_error_train += abs(
                        float(actual) - prediction
                    )
                    train_history.append(actual)

                wape_train = (
                    absolute_error_train
                    / train_denominator
                    * 100.0
                )
                mae_train = (
                    absolute_error_train
                    / float(len(train_evaluation))
                )

                is_better = (
                    best_wape_train is None
                    or wape_train
                    < best_wape_train - 1e-12
                )

                same_error_lower_alpha = (
                    best_wape_train is not None
                    and abs(
                        wape_train - best_wape_train
                    ) <= 1e-12
                    and (
                        best_alpha is None
                        or alpha < best_alpha
                    )
                )

                if is_better or same_error_lower_alpha:
                    best_alpha = alpha
                    best_wape_train = wape_train
                    best_mae_train = mae_train

            if (
                best_alpha is None
                or best_wape_train is None
                or best_mae_train is None
            ):
                raise ValueError(
                    "Alpha terbaik tidak dapat ditentukan "
                    "dari data train."
                )

            # Test hanya evaluasi dengan alpha yang sudah dikunci.
            test_history = list(train)
            absolute_error_test = 0.0

            for actual in test:
                prediction = forecast_brown(
                    actuals=test_history,
                    alpha=best_alpha,
                )

                absolute_error_test += abs(
                    float(actual) - prediction
                )
                test_history.append(actual)

            mae_test = (
                absolute_error_test
                / float(len(test))
            )
            wape_test = (
                absolute_error_test
                / test_denominator
                * 100.0
            )

            results.append(
                {
                    "produk_id": product.produk_id,
                    "alpha": round(best_alpha, 2),
                    "mae_train": round(
                        best_mae_train,
                        4,
                    ),
                    "wape_train": round(
                        best_wape_train,
                        4,
                    ),
                    "mae_test": round(mae_test, 4),
                    "wape_test": round(wape_test, 4),
                }
            )

        return {"results": results}
