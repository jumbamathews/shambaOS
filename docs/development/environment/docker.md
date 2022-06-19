# Docker

## Docker build arguments

The shambaOS Docker images allow certain variables to be overridden at
image build time using the `--build-arg` parameter of `docker build`.

Available arguments and their default values are described below:

- `shambaOS_REPO` - The shambaOS Git repository URL.
    - Default: `https://github.com/shambaOS/shambaOS.git`
- `shambaOS_VERSION` - The shambaOS Git branch/tag/commit to check out.
    - Default: `2.x`
- `PROJECT_REPO` - The shambaOS Composer project Git repository URL.
    - Default: `https://github.com/shambaOS/composer-project.git`
- `PROJECT_VERSION` - The shambaOS Composer project Git branch/tag/commit to
  check out.
    - Default: `2.x`

The `2.x-dev` image also provides the following:

- `WWW_DATA_ID` - The ID to use for the `www-data` user and group inside the
   image. Setting this to the ID of the developer's user on the host machine
   allows Composer to create files owned by www-data inside the container,
   while keeping those files editable by the developer outside of the
   container. If your user ID is not `1000`, build the image with:
   `--build-arg WWW_DATA_ID=$(id -u)`
    - Default: `1000`
