# check if open-transposh.php file exists
if [ ! -f open-transposh.php ]; then
  cd ../
  if [ ! -f open-transposh.php ]; then
    echo "open-transposh.php file not found"
    exit 1
  fi
fi

# MINOR version when you add functionality in a backwards compatible manner
deno run --allow-run --allow-read --allow-write build/bump-version.ts minor
exit 0;
