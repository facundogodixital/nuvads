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

- Darle al servidor web de producción el mismo tiempo de espera que tiene nginx en local, `fastcgi_read_timeout 130s`
  en el bloque que pasa los pedidos a PHP. Lo necesitan la generación de ideas y la escritura de la pieza, que
  esperan al modelo hasta 120 segundos.
