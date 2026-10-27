import logging

from typing import Any

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from app.schemas.des_schema import (
    DesBatchRequest,
    DesBatchResponse,
    DesCalibrationRequest,
    DesCalibrationResponse,
)
from app.schemas.kmeans_schema import (
    KMeansRequest,
    KMeansResponse,
)
from app.services.des_service import DesService
from app.services.kmeans_service import KMeansService


logger = logging.getLogger(__name__)


app = FastAPI(
    title="SR12 Analytics Service",
    description=(
        "Layanan analitik untuk K-Means Clustering "
        "dan Double Exponential Smoothing Brown."
    ),
    version="1.3.0",
    docs_url="/docs",
    redoc_url="/redoc",
    openapi_url="/openapi.json",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://127.0.0.1:8000",
        "http://localhost:8000",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

kmeans_service = KMeansService()
des_service = DesService()


@app.get("/")
def index() -> dict[str, str]:
    return {
        "message": "SR12 Analytics Service is running"
    }


@app.get("/health")
def health() -> dict[str, str]:
    return {
        "status": "ok",
        "service": "sr12-analytics",
    }


@app.post(
    "/api/kmeans/analyze",
    response_model=KMeansResponse,
)
def analyze_kmeans(payload: KMeansRequest) -> Any:
    try:
        return kmeans_service.analyze(payload)
    except ValueError as error:
        raise HTTPException(
            status_code=422,
            detail=str(error),
        ) from error
    except Exception as error:
        logger.exception(
            "Terjadi kesalahan internal pada proses K-Means."
        )

        raise HTTPException(
            status_code=500,
            detail="Terjadi kesalahan internal pada proses K-Means.",
        ) from error


@app.post(
    "/api/v1/des/forecast-batch",
    response_model=DesBatchResponse,
)
def forecast_des_batch(
    payload: DesBatchRequest,
) -> Any:
    try:
        return des_service.forecast_batch(payload)
    except ValueError as error:
        raise HTTPException(
            status_code=422,
            detail=str(error),
        ) from error
    except Exception as error:
        logger.exception(
            "Terjadi kesalahan internal pada proses DES."
        )

        raise HTTPException(
            status_code=500,
            detail="Terjadi kesalahan internal pada proses DES.",
        ) from error


@app.post(
    "/api/v1/des/calibrate-batch",
    response_model=DesCalibrationResponse,
)
def calibrate_des_batch(
    payload: DesCalibrationRequest,
) -> Any:
    try:
        return des_service.calibrate_batch(payload)
    except ValueError as error:
        raise HTTPException(
            status_code=422,
            detail=str(error),
        ) from error
    except Exception as error:
        logger.exception(
            "Terjadi kesalahan internal pada "
            "proses kalibrasi DES."
        )
        raise HTTPException(
            status_code=500,
            detail=(
                "Terjadi kesalahan internal pada "
                "proses kalibrasi DES."
            ),
        ) from error
