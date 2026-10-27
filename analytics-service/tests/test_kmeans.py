from typing import Any

from fastapi.testclient import TestClient

from app.main import app
from app.services.kmeans_service import KMeansService


client = TestClient(app)


def valid_kmeans_payload() -> dict[str, Any]:
    return {
        "products": [
            {
                "produk_id": 1,
                "recency": 30,
                "frekuensi": 1,
                "qty": 10,
                "omzet": 100_000,
            },
            {
                "produk_id": 2,
                "recency": 29,
                "frekuensi": 2,
                "qty": 15,
                "omzet": 150_000,
            },
            {
                "produk_id": 3,
                "recency": 15,
                "frekuensi": 12,
                "qty": 150,
                "omzet": 7_500_000,
            },
            {
                "produk_id": 4,
                "recency": 14,
                "frekuensi": 13,
                "qty": 170,
                "omzet": 8_000_000,
            },
            {
                "produk_id": 5,
                "recency": 1,
                "frekuensi": 30,
                "qty": 600,
                "omzet": 30_000_000,
            },
            {
                "produk_id": 6,
                "recency": 0,
                "frekuensi": 31,
                "qty": 650,
                "omzet": 32_000_000,
            },
        ],
    }




def test_kmeans_uses_reproducible_random_initialization() -> None:
    assert KMeansService.N_CLUSTERS == 3
    assert KMeansService.INIT_METHOD == "random"
    assert KMeansService.N_INIT == 1
    assert KMeansService.RANDOM_STATE == 42
    assert KMeansService.MAX_ITER == 300
    assert KMeansService.TOLERANCE == 0.0001
    assert KMeansService.ALGORITHM == "lloyd"

def test_kmeans_analyze_success() -> None:
    response = client.post(
        "/api/kmeans/analyze",
        json=valid_kmeans_payload(),
    )

    assert response.status_code == 200

    body = response.json()

    assert set(body.keys()) == {
        "k",
        "iterasi",
        "results",
    }

    assert body["k"] == 3
    assert body["iterasi"] >= 1
    assert len(body["results"]) == 6

    results_by_product = {
        result["produk_id"]: result
        for result in body["results"]
    }
    expected_clusters = {
        1: 3,
        2: 3,
        3: 3,
        4: 3,
        5: 2,
        6: 1,
    }

    actual_clusters = {
        product_id: results_by_product[product_id]["cluster"]
        for product_id in sorted(results_by_product)
    }

    assert actual_clusters == expected_clusters


    assert results_by_product[1]["recency"] == 30
    assert results_by_product[6]["recency"] == 0


def test_kmeans_recency_kecil_memiliki_nilai_prioritas_lebih_tinggi() -> None:
    payload = {
        "products": [
            {
                "produk_id": 1,
                "recency": 30,
                "frekuensi": 10,
                "qty": 100,
                "omzet": 1_000_000,
            },
            {
                "produk_id": 2,
                "recency": 15,
                "frekuensi": 10,
                "qty": 100,
                "omzet": 1_000_000,
            },
            {
                "produk_id": 3,
                "recency": 0,
                "frekuensi": 10,
                "qty": 100,
                "omzet": 1_000_000,
            },
        ],
    }

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 200

    results_by_product = {
        result["produk_id"]: result
        for result in response.json()["results"]
    }

    assert results_by_product[3]["cluster"] == 1
    assert results_by_product[2]["cluster"] == 2
    assert results_by_product[1]["cluster"] == 3


def test_kmeans_result_is_deterministic() -> None:
    payload = valid_kmeans_payload()

    first_response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    second_response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert first_response.status_code == 200
    assert second_response.status_code == 200

    assert (
        first_response.json()
        == second_response.json()
    )


def test_kmeans_response_does_not_use_obsolete_fields() -> None:
    response = client.post(
        "/api/kmeans/analyze",
        json=valid_kmeans_payload(),
    )

    assert response.status_code == 200

    body = response.json()

    serialized = str(body).lower()

    obsolete_fields = [
        "best_k",
        "best_dbi",
        "dbi",
        "evaluations",
        "k_candidates",
        "centroid_history",
    ]

    for obsolete_field in obsolete_fields:
        assert obsolete_field not in serialized


def test_kmeans_rejects_less_than_three_products() -> None:
    payload = valid_kmeans_payload()

    payload["products"] = (
        payload["products"][:2]
    )

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422


def test_kmeans_rejects_duplicate_product_ids() -> None:
    payload = valid_kmeans_payload()

    payload["products"][1]["produk_id"] = 1

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422


def test_kmeans_rejects_negative_recency() -> None:
    payload = valid_kmeans_payload()

    payload["products"][0]["recency"] = -1

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422


def test_kmeans_rejects_negative_other_attributes() -> None:
    payload = valid_kmeans_payload()

    payload["products"][0]["qty"] = -1

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422


def test_kmeans_rejects_less_than_three_unique_feature_rows() -> None:
    payload = {
        "products": [
            {
                "produk_id": 1,
                "recency": 10,
                "frekuensi": 1,
                "qty": 10,
                "omzet": 100_000,
            },
            {
                "produk_id": 2,
                "recency": 10,
                "frekuensi": 1,
                "qty": 10,
                "omzet": 100_000,
            },
            {
                "produk_id": 3,
                "recency": 5,
                "frekuensi": 2,
                "qty": 20,
                "omzet": 200_000,
            },
        ],
    }

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422

    assert (
        "minimal 3 kombinasi nilai"
        in response.json()["detail"]
    )


def test_kmeans_rejects_client_algorithm_configuration() -> None:
    payload = valid_kmeans_payload()

    payload["k"] = 4
    payload["random_state"] = 100

    response = client.post(
        "/api/kmeans/analyze",
        json=payload,
    )

    assert response.status_code == 422
