Symfony API Demo

Project Setup

1. Install Symfony CLI. This creates a binary called symfony that provides all the tools you need to develop and run your Symfony application locally. For Windows follow this page: https://symfony.com/download

  ```bash
  curl -sS https://get.symfony.com/cli/installer | bash
  ```

2. Run this command to start your database container. If you are using the Symfony Binary, it will detect the new service automatically. Port 3306 will be exposed to a random port on your host machine.

  ```bash
  docker-compose up -d
  ```
  
3. Run this command to see the environment variables the binary is exposing. This will override any values you have in your .env files. (optional)

  ```bash
  symfony var:export --multiline
  ```

4. To log into your mysql server run following. If it fails give it a little time and try again (docker may still be creating on creating it)

  ```bash
  docker-compose exec database mysql -u root --password=password
  ```
  
5. Start local web server:
  ```bash
  symfony serve
  ```
  
6. `main` database may already exist. If not, run:
  ```bash
  symfony console doctrine:database:create
  ```

7. Load db migrations:
  ```bash
  symfony console  doctrine:migrations:migrate
  ```

8. Load fixtures: This will create a user `admin@example.com` with password `secret` for testing authentication. (select yes option)
  ```bash
  symfony console doctrine:fixtures:load
  ```
  
8. Generate public and Private Keys (your keys will land in `config/jwt/private.pem` and `config/jwt/public.pem`
  ```bash
  symfony console lexik:jwt:generate-keypair
  ```
  
9. Run Unit Tests
  ```bash
  ./bin/phpunit
  ```
