# Pendientes

- Revisar el `retry_after` de los jobs y sus timeouts.
- Revisar los límites de subida de PHP y nginx, en cantidad de archivos y tamaño.
- Terminar S3: el audio y el zip de WhatsApp siguen en el disco local mientras se procesan, y `payload_s3_path`
  todavía no se usa.
- Poder leer videos y reels.
- Adaptar el caso de las fuentes de `KnowledgePersistenceTest::isolates_reads_and_soft_deletes`, anulado el
  01/10/2026: borra un audio, y solo se pueden borrar fotos y documentos.
