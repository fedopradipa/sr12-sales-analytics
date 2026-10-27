from typing import Any

import numpy as np
from sklearn.cluster import KMeans
from sklearn.preprocessing import MinMaxScaler

from app.schemas.kmeans_schema import KMeansRequest


class KMeansService:
    N_CLUSTERS = 3
    INIT_METHOD = "random"
    N_INIT = 1
    MAX_ITER = 300
    TOLERANCE = 0.0001
    RANDOM_STATE = 42
    ALGORITHM = "lloyd"

    RECENCY_COLUMN_INDEX = 0
    FREQUENCY_COLUMN_INDEX = 1
    QUANTITY_COLUMN_INDEX = 2
    MONETARY_COLUMN_INDEX = 3

    def analyze(
        self,
        payload: KMeansRequest,
    ) -> dict[str, Any]:
        self._validate_feature_variation(
            payload
        )

        raw_features = self._build_feature_matrix(
            payload
        )

        normalized_features = (
            self._normalize_features(
                raw_features
            )
        )

        model = KMeans(
            n_clusters=self.N_CLUSTERS,
            init=self.INIT_METHOD,
            n_init=self.N_INIT,
            max_iter=self.MAX_ITER,
            tol=self.TOLERANCE,
            random_state=self.RANDOM_STATE,
            algorithm=self.ALGORITHM,
        )

        raw_labels = model.fit_predict(
            normalized_features
        )

        self._validate_cluster_result(
            raw_labels
        )

        ranked_cluster_map = (
            self._build_ranked_cluster_map(
                model.cluster_centers_
            )
        )

        results = []

        for product, raw_cluster in zip(
            payload.products,
            raw_labels,
            strict=True,
        ):
            ranked_cluster = (
                ranked_cluster_map[
                    int(raw_cluster)
                ]
            )

            results.append(
                {
                    "produk_id":
                        product.produk_id,

                    "recency":
                        product.recency,

                    "frekuensi":
                        product.frekuensi,

                    "qty":
                        product.qty,

                    "omzet":
                        product.omzet,

                    "cluster":
                        ranked_cluster,
                }
            )

        results.sort(
            key=lambda item: (
                item["cluster"],
                item["recency"],
                -item["omzet"],
                -item["qty"],
                -item["frekuensi"],
                item["produk_id"],
            )
        )

        return {
            "k": self.N_CLUSTERS,
            "iterasi": int(model.n_iter_),
            "results": results,
        }

    def _build_feature_matrix(
        self,
        payload: KMeansRequest,
    ) -> np.ndarray:
        return np.array(
            [
                [
                    product.recency,
                    product.frekuensi,
                    product.qty,
                    product.omzet,
                ]
                for product in payload.products
            ],
            dtype=np.float64,
        )

    def _normalize_features(
        self,
        raw_features: np.ndarray,
    ) -> np.ndarray:
        """
        Melakukan Min-Max Scaling yang sama terhadap seluruh
        atribut RFMQ.

        Arah atribut setelah normalisasi tetap mengikuti makna
        aslinya:
        - Recency   : semakin kecil semakin baik/aktif.
        - Frequency : semakin besar semakin baik/aktif.
        - Quantity  : semakin besar semakin baik/aktif.
        - Monetary  : semakin besar semakin baik/aktif.

        Recency TIDAK dibalik pada tahap normalisasi. Perbedaan
        arah Recency diperhitungkan hanya ketika memberi peringkat
        hasil cluster pada _build_ranked_cluster_map().
        """
        scaler = MinMaxScaler()

        return scaler.fit_transform(
            raw_features
        )

    def _validate_feature_variation(
        self,
        payload: KMeansRequest,
    ) -> None:
        unique_features = {
            (
                product.recency,
                product.frekuensi,
                product.qty,
                product.omzet,
            )
            for product in payload.products
        }

        if (
            len(unique_features)
            < self.N_CLUSTERS
        ):
            raise ValueError(
                "Data K-Means harus memiliki minimal "
                "3 kombinasi nilai recency, frekuensi, "
                "qty, dan omzet yang berbeda."
            )

    def _validate_cluster_result(
        self,
        labels: np.ndarray,
    ) -> None:
        total_clusters = len(
            np.unique(labels)
        )

        if (
            total_clusters
            != self.N_CLUSTERS
        ):
            raise ValueError(
                "Proses K-Means tidak berhasil "
                "membentuk tepat 3 cluster."
            )

    def _build_ranked_cluster_map(
        self,
        normalized_centroids: np.ndarray,
    ) -> dict[int, int]:
        """
        Memberi peringkat cluster dengan arah atribut RFMQ:

        Recency   : lebih kecil = lebih baik.
        Frequency : lebih besar = lebih baik.
        Quantity  : lebih besar = lebih baik.
        Monetary  : lebih besar = lebih baik.

        Karena centroid Recency berasal dari Min-Max biasa,
        kontribusinya terhadap skor dibuat (1 - recency).
        Transformasi ini hanya digunakan untuk INTERPRETASI /
        PELABELAN cluster, bukan untuk proses K-Means.

        Skor:
            ((1 - R) + F + Q + M) / 4

        Skor terbesar  -> Cluster 1 (Pergerakan Tinggi)
        Skor berikutnya -> Cluster 2 (Pergerakan Sedang)
        Skor terkecil  -> Cluster 3 (Pergerakan Rendah)
        """
        cluster_scores = []

        for raw_cluster in range(
            self.N_CLUSTERS
        ):
            centroid = (
                normalized_centroids[
                    raw_cluster
                ]
            )

            recency = float(
                centroid[
                    self.RECENCY_COLUMN_INDEX
                ]
            )

            frequency = float(
                centroid[
                    self.FREQUENCY_COLUMN_INDEX
                ]
            )

            quantity = float(
                centroid[
                    self.QUANTITY_COLUMN_INDEX
                ]
            )

            monetary = float(
                centroid[
                    self.MONETARY_COLUMN_INDEX
                ]
            )

            score = (
                (1.0 - recency)
                + frequency
                + quantity
                + monetary
            ) / 4.0

            cluster_scores.append(
                {
                    "raw_cluster":
                        raw_cluster,

                    "score":
                        score,
                }
            )

        cluster_scores.sort(
            key=lambda item: (
                -item["score"],
                item["raw_cluster"],
            )
        )

        return {
            item["raw_cluster"]:
                rank
            for rank, item in enumerate(
                cluster_scores,
                start=1,
            )
        }
