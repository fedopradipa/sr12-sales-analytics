from typing import Literal

from pydantic import (
    BaseModel,
    ConfigDict,
    Field,
    model_validator,
)


class ProductKMeansData(BaseModel):
    model_config = ConfigDict(
        extra="forbid",
    )

    produk_id: int = Field(
        ...,
        gt=0,
    )

    recency: int = Field(
        ...,
        ge=0,
    )

    frekuensi: int = Field(
        ...,
        ge=0,
    )

    qty: int = Field(
        ...,
        ge=0,
    )

    omzet: int = Field(
        ...,
        ge=0,
    )


class KMeansRequest(BaseModel):
    model_config = ConfigDict(
        extra="forbid",
    )

    products: list[ProductKMeansData] = Field(
        ...,
        min_length=3,
    )

    @model_validator(mode="after")
    def validate_unique_product_ids(
        self,
    ) -> "KMeansRequest":
        product_ids = [
            product.produk_id
            for product in self.products
        ]

        if len(product_ids) != len(set(product_ids)):
            raise ValueError(
                "produk_id pada data K-Means tidak boleh duplikat."
            )

        return self


class KMeansResultResponse(BaseModel):
    produk_id: int

    recency: int
    frekuensi: int
    qty: int
    omzet: int

    cluster: int = Field(
        ...,
        ge=1,
        le=3,
    )


class KMeansResponse(BaseModel):
    k: Literal[3]

    iterasi: int = Field(
        ...,
        ge=1,
    )

    results: list[KMeansResultResponse] = Field(
        ...,
        min_length=3,
    )
