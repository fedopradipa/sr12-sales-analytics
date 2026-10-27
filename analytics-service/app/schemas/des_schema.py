from datetime import date

from pydantic import BaseModel, ConfigDict, Field, model_validator


class DesSeriesItem(BaseModel):
    model_config = ConfigDict(extra="forbid")

    periode: date
    aktual: int = Field(..., ge=0)


class DesBatchProduct(BaseModel):
    model_config = ConfigDict(extra="forbid")

    produk_id: int = Field(..., gt=0)
    alpha: float = Field(..., ge=0.01, le=0.99)
    series: list[DesSeriesItem] = Field(
        ...,
        min_length=2,
        max_length=240,
    )

    @model_validator(mode="after")
    def validate_monthly_series(self) -> "DesBatchProduct":
        periods = [item.periode for item in self.series]

        if any(period.day != 1 for period in periods):
            raise ValueError(
                "Setiap periode DES harus menggunakan tanggal "
                "hari pertama pada bulan tersebut."
            )

        if periods != sorted(periods):
            raise ValueError(
                "Deret periode DES harus diurutkan dari bulan "
                "paling awal ke bulan paling akhir."
            )

        if len(periods) != len(set(periods)):
            raise ValueError(
                "Periode DES tidak boleh duplikat."
            )

        for index in range(1, len(periods)):
            expected_period = self._next_month(
                periods[index - 1]
            )

            if periods[index] != expected_period:
                raise ValueError(
                    "Deret periode DES harus berurutan setiap "
                    "bulan tanpa periode yang terlewat."
                )

        if not any(item.aktual > 0 for item in self.series):
            raise ValueError(
                "Deret DES harus memiliki minimal satu nilai "
                "penjualan positif."
            )

        return self

    @staticmethod
    def _next_month(period: date) -> date:
        if period.month == 12:
            return date(period.year + 1, 1, 1)

        return date(period.year, period.month + 1, 1)


class DesBatchRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    products: list[DesBatchProduct] = Field(
        ...,
        min_length=1,
        max_length=500,
    )

    @model_validator(mode="after")
    def validate_batch(self) -> "DesBatchRequest":
        product_ids = [
            product.produk_id
            for product in self.products
        ]

        if len(product_ids) != len(set(product_ids)):
            raise ValueError(
                "Produk pada batch DES tidak boleh duplikat."
            )

        first_periods = [
            item.periode
            for item in self.products[0].series
        ]

        for product in self.products[1:]:
            periods = [
                item.periode
                for item in product.series
            ]

            if periods != first_periods:
                raise ValueError(
                    "Seluruh produk batch DES harus menggunakan "
                    "periode historis yang sama."
                )

        return self


class DesForecastResult(BaseModel):
    model_config = ConfigDict(extra="forbid")

    produk_id: int = Field(..., gt=0)
    prediksi: float = Field(..., ge=0)


class DesBatchResponse(BaseModel):
    model_config = ConfigDict(extra="forbid")

    results: list[DesForecastResult] = Field(
        ...,
        min_length=1,
    )


class DesCalibrationProduct(BaseModel):
    model_config = ConfigDict(extra="forbid")

    produk_id: int = Field(..., gt=0)
    series: list[DesSeriesItem] = Field(
        ...,
        min_length=3,
        max_length=240,
    )

    @model_validator(mode="after")
    def validate_monthly_series(
        self,
    ) -> "DesCalibrationProduct":
        periods = [item.periode for item in self.series]

        if any(period.day != 1 for period in periods):
            raise ValueError(
                "Setiap periode DES harus menggunakan tanggal "
                "hari pertama pada bulan tersebut."
            )

        if periods != sorted(periods):
            raise ValueError(
                "Deret periode DES harus diurutkan dari bulan "
                "paling awal ke bulan paling akhir."
            )

        if len(periods) != len(set(periods)):
            raise ValueError(
                "Periode DES tidak boleh duplikat."
            )

        for index in range(1, len(periods)):
            previous = periods[index - 1]

            if previous.month == 12:
                expected = date(previous.year + 1, 1, 1)
            else:
                expected = date(
                    previous.year,
                    previous.month + 1,
                    1,
                )

            if periods[index] != expected:
                raise ValueError(
                    "Deret periode DES harus berurutan setiap "
                    "bulan tanpa periode yang terlewat."
                )

        if not any(
            item.aktual > 0
            for item in self.series
        ):
            raise ValueError(
                "Deret DES harus memiliki minimal satu nilai "
                "penjualan positif."
            )

        return self


class DesCalibrationRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    test_months: int = Field(..., ge=1, le=120)
    products: list[DesCalibrationProduct] = Field(
        ...,
        min_length=1,
        max_length=500,
    )

    @model_validator(mode="after")
    def validate_calibration(
        self,
    ) -> "DesCalibrationRequest":
        product_ids = [
            product.produk_id
            for product in self.products
        ]

        if len(product_ids) != len(set(product_ids)):
            raise ValueError(
                "Produk kalibrasi DES tidak boleh duplikat."
            )

        for product in self.products:
            if len(product.series) < self.test_months + 2:
                raise ValueError(
                    "Setiap produk harus memiliki minimal dua "
                    "bulan data train sebelum periode test."
                )

            train_series = product.series[:-self.test_months]
            train_evaluation = train_series[1:]
            test_series = product.series[-self.test_months:]

            if not any(item.aktual > 0 for item in train_evaluation):
                raise ValueError(
                    "Data train yang dievaluasi harus memiliki "
                    "minimal satu penjualan positif."
                )

            if not any(item.aktual > 0 for item in test_series):
                raise ValueError(
                    "Data test setiap produk harus memiliki "
                    "minimal satu penjualan positif."
                )

        return self


class DesCalibrationResult(BaseModel):
    model_config = ConfigDict(extra="forbid")

    produk_id: int = Field(..., gt=0)
    alpha: float = Field(..., ge=0.01, le=0.99)
    mae_train: float = Field(..., ge=0)
    wape_train: float = Field(..., ge=0)
    mae_test: float = Field(..., ge=0)
    wape_test: float = Field(..., ge=0)


class DesCalibrationResponse(BaseModel):
    model_config = ConfigDict(extra="forbid")

    results: list[DesCalibrationResult] = Field(
        ...,
        min_length=1,
        max_length=500,
    )
