@echo off
set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%

if not exist "C:\sistema-entregas\backups" mkdir "C:\sistema-entregas\backups"

docker compose -f "C:\sistema-entregas\docker-compose.prod.yml" exec -T db pg_dump -U entregas_user sistema_entregas > "C:\sistema-entregas\backups\backup_entregas_%DATA%.sql"