from fastapi.testclient import TestClient

from app.main import app


client = TestClient(app)


def test_root_endpoint() -> None:
    response = client.get("/")

    assert response.status_code == 200
    assert response.json() == {
        "message": "SR12 Analytics Service is running",
    }


def test_health_endpoint() -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "service": "sr12-analytics",
    }


def test_openapi_documentation_is_available() -> None:
    response = client.get("/openapi.json")

    assert response.status_code == 200

    document = response.json()

    assert document["info"]["title"] == (
        "SR12 Analytics Service"
    )

    assert "/health" in document["paths"]
    assert "/api/kmeans/analyze" in document["paths"]
    assert "/api/v1/des/forecast-batch" in document["paths"]
    assert "/api/des/analyze" not in document["paths"]
    assert "/api/des/analyze-batch" not in document["paths"]


def test_swagger_ui_is_available() -> None:
    response = client.get("/docs")

    assert response.status_code == 200
    assert "text/html" in response.headers[
        "content-type"
    ]
    assert "Swagger UI" in response.text