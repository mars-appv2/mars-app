#!/bin/bash

# Buat direktori export
mkdir -p export_info

echo "Exporting views..."
find resources/views -name "*.blade.php" > export_info/views_structure.txt

echo "Exporting routes..."
cat routes/web.php > export_info/routes_web.txt
cat routes/api.php > export_info/routes_api.txt 2>/dev/null

echo "Exporting controllers..."
find app/Http/Controllers -name "*.php" > export_info/controllers_list.txt

echo "Exporting menu files..."
find resources/views -name "*sidebar*" -o -name "*menu*" -o -name "*nav*" | head -10 > export_info/menu_files.txt

echo "Creating summary..."
php artisan --version > export_info/summary.txt
echo "Export completed!"
echo "Files are in export_info/ directory"
ls -la export_info/
