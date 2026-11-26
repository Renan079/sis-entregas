@echo off
set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%
if not exist "..\..\backups" mkdir "..\..\backups"

docker exec entregas_db_prod pg_dump -U entregas_user sistema_entregas > "..\..\backups\backup_entregas_%DATA%.sql"
echo Backup concluido em backups\backup_entregas_%DATA%.sql
pause