# Pendientes de producción

- Generar la clave de acceso del usuario IAM `nuvads-s3-production-user`, que solo tiene acceso al bucket
  `nuvads-production`, y cargarla en el entorno del servidor de producción.

  ```bash
  aws iam create-access-key --user-name nuvads-s3-production-user --profile nuvads-ff-aws-cli
  ```

  ```text
  AWS_ACCESS_KEY_ID=...
  AWS_SECRET_ACCESS_KEY=...
  AWS_DEFAULT_REGION=us-east-1
  AWS_BUCKET=nuvads-production
  ```
