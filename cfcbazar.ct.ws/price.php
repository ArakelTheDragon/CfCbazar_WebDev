<!DOCTYPE html>
<html>
<head>
  <title>Set WorkToken Price</title>
  <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.min.js"></script>
</head>
<body>
  <h2>Set WorkToken Market Price</h2>
  <label for="price">New Price (in wei):</label>
  <input type="text" id="price" placeholder="e.g. 100000000000">
  <button onclick="setPrice()">Set Price</button>

  <p id="status"></p>

  <script>
    const proxyAddress = "0xecbD4E86EE8583c8681E2eE2644FC778848B237D";
    const abi = [
      {
        "inputs": [{"internalType": "uint256", "name": "newPrice", "type": "uint256"}],
        "name": "setMarketPrice",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      }
    ];

    async function setPrice() {
      try {
        const price = document.getElementById("price").value;
        if (!price || isNaN(price)) {
          document.getElementById("status").innerText = "Please enter a valid number.";
          return;
        }

        await window.ethereum.request({ method: 'eth_requestAccounts' });
        const provider = new ethers.providers.Web3Provider(window.ethereum);
        const signer = provider.getSigner();
        const contract = new ethers.Contract(proxyAddress, abi, signer);

        const tx = await contract.setMarketPrice(price);
        document.getElementById("status").innerText = "Transaction sent: " + tx.hash;

        await tx.wait();
        document.getElementById("status").innerText = "Price updated successfully!";
      } catch (err) {
        console.error(err);
        document.getElementById("status").innerText = "Error: " + err.message;
      }
    }
  </script>
</body>
</html>