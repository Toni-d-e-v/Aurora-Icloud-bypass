echo "Aurora IOS remove old icloud account, ONLY WORKS ON JAILBROKEN IPHONES"
echo "Checking for python3..."
if ! [ -x "$(command -v python3)" ]; then
  echo 'Error: python3 is not installed.' >&2
  exit 1
else
  echo "python3 is installed"
fi
echo "Checking for pip3..."
if ! [ -x "$(command -v pip3)" ]; then
  echo 'Error: pip3 is not installed.' >&2
  exit 1
else
  echo "pip3 is installed"
fi
echo "Installing required libs..."
sudo pip3 install paramiko
echo "Launching shell"
python3 ./source/scripts/remove_oldicloud.py
