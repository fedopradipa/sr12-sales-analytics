from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.domain.brown_des import forecast_brown
from app.main import app


client = TestClient(app)


def product_payload(
    product_id: int = 10,
    alpha: float = 0.25,
) -> dict[str, Any]:
    actuals = [
        10,
        12,
        15,
        14,
        18,
        20,
        21,
        25,
        24,
        28,
        30,
        32,
    ]

    return {
        "produk_id": product_id,
        "alpha": alpha,
        "series": [
            {
                "periode": f"2024-{month:02d}-01",
                "aktual": actual,
            }
            for month, actual in enumerate(
                actuals,
                start=1,
            )
        ],
    }


def test_domain_brown_menghasilkan_prediksi_deterministik() -> None:
    prediction = forecast_brown(
        actuals=[
            10,
            12,
            15,
            14,
            18,
            20,
            21,
            25,
            24,
            28,
            30,
            32,
        ],
        alpha=0.25,
    )

    assert prediction == pytest.approx(
        32.8229780197,
        abs=0.000001,
    )


def test_batch_fixed_alpha_berhasil() -> None:
    response = client.post(
        "/api/v1/des/forecast-batch",
        json={
            "products": [
                product_payload(
                    product_id=10,
                    alpha=0.25,
                ),
                product_payload(
                    product_id=11,
                    alpha=0.40,
                ),
            ]
        },
    )

    assert response.status_code == 200

    body = response.json()

    assert set(body) == {"results"}
    assert len(body["results"]) == 2

    assert body["results"][0]["produk_id"] == 10
    assert body["results"][0]["prediksi"] == pytest.approx(
        32.823,
        abs=0.0001,
    )

    assert body["results"][1]["produk_id"] == 11
    assert body["results"][1]["prediksi"] >= 0

    assert set(body["results"][0]) == {
        "produk_id",
        "prediksi",
    }


def test_alpha_wajib_dikirim() -> None:
    payload = product_payload()
    payload.pop("alpha")

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_alpha_null_ditolak() -> None:
    payload = product_payload()
    payload["alpha"] = None

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_field_training_lama_ditolak() -> None:
    payload = product_payload()
    payload["mae_train"] = 2.5

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_batch_menolak_produk_duplikat() -> None:
    response = client.post(
        "/api/v1/des/forecast-batch",
        json={
            "products": [
                product_payload(product_id=10),
                product_payload(product_id=10),
            ]
        },
    )

    assert response.status_code == 422


def test_batch_menolak_periode_produk_berbeda() -> None:
    second = product_payload(product_id=11)
    second["series"][0]["periode"] = "2023-12-01"

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={
            "products": [
                product_payload(product_id=10),
                second,
            ]
        },
    )

    assert response.status_code == 422


def test_deret_minimal_dua_periode() -> None:
    payload = product_payload()
    payload["series"] = payload["series"][:1]

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_deret_harus_berurutan_tanpa_bulan_terlewat() -> None:
    payload = product_payload()
    payload["series"][5]["periode"] = "2024-08-01"

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_penjualan_negatif_ditolak() -> None:
    payload = product_payload()
    payload["series"][3]["aktual"] = -1

    response = client.post(
        "/api/v1/des/forecast-batch",
        json={"products": [payload]},
    )

    assert response.status_code == 422


def test_endpoint_des_lama_tidak_tersedia() -> None:
    response = client.post(
        "/api/des/analyze",
        json=product_payload(),
    )

    assert response.status_code == 404


def calibration_payload(
    test_months: int = 3,
) -> dict[str, Any]:
    payload = product_payload(
        product_id=91,
        alpha=0.25,
    )
    payload.pop("alpha")

    return {
        "test_months": test_months,
        "products": [payload],
    }


def test_kalibrasi_alpha_batch_berhasil() -> None:
    response = client.post(
        "/api/v1/des/calibrate-batch",
        json=calibration_payload(),
    )

    assert response.status_code == 200

    body = response.json()
    assert set(body) == {"results"}
    assert len(body["results"]) == 1

    result = body["results"][0]
    assert result["produk_id"] == 91
    assert 0.01 <= result["alpha"] <= 0.99
    assert result["mae_train"] >= 0
    assert result["wape_train"] >= 0
    assert result["mae_test"] >= 0
    assert result["wape_test"] >= 0


def test_kalibrasi_alpha_deterministik() -> None:
    payload = calibration_payload()

    first = client.post(
        "/api/v1/des/calibrate-batch",
        json=payload,
    )
    second = client.post(
        "/api/v1/des/calibrate-batch",
        json=payload,
    )

    assert first.status_code == 200
    assert second.status_code == 200
    assert first.json() == second.json()


def test_kalibrasi_menolak_test_terlalu_besar() -> None:
    response = client.post(
        "/api/v1/des/calibrate-batch",
        json=calibration_payload(
            test_months=11,
        ),
    )

    assert response.status_code == 422


def test_kalibrasi_menolak_field_alpha_dari_client() -> None:
    payload = calibration_payload()
    payload["products"][0]["alpha"] = 0.40

    response = client.post(
        "/api/v1/des/calibrate-batch",
        json=payload,
    )

    assert response.status_code == 422

def test_domain_brown_satu_periode_menghasilkan_forecast_periode_kedua() -> None:
    prediction = forecast_brown(
        actuals=[10],
        alpha=0.25,
    )

    assert prediction == pytest.approx(
        10.0,
        abs=0.000001,
    )


def test_kalibrasi_memilih_alpha_dari_train_bukan_test() -> None:
    base = calibration_payload(test_months=3)
    base["products"][0]["series"] = (
        base["products"][0]["series"][:9]
        + [
            {"periode": "2024-10-01", "aktual": 28},
            {"periode": "2024-11-01", "aktual": 30},
            {"periode": "2024-12-01", "aktual": 32},
        ]
    )

    changed_test = calibration_payload(test_months=3)
    changed_test["products"][0]["series"] = (
        changed_test["products"][0]["series"][:9]
        + [
            {"periode": "2024-10-01", "aktual": 1},
            {"periode": "2024-11-01", "aktual": 1},
            {"periode": "2024-12-01", "aktual": 1},
        ]
    )

    first = client.post(
        "/api/v1/des/calibrate-batch",
        json=base,
    )
    second = client.post(
        "/api/v1/des/calibrate-batch",
        json=changed_test,
    )

    assert first.status_code == 200
    assert second.status_code == 200

    first_result = first.json()["results"][0]
    second_result = second.json()["results"][0]

    assert first_result["alpha"] == pytest.approx(0.42)
    assert second_result["alpha"] == pytest.approx(0.42)
    assert (
        first_result["wape_train"]
        == second_result["wape_train"]
    )
    assert (
        first_result["mae_train"]
        == second_result["mae_train"]
    )
    assert (
        first_result["wape_test"]
        != second_result["wape_test"]
    )
