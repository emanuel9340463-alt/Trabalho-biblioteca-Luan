(() => {
  const openButton = document.querySelector("[data-open-isbn-scanner]");
  const dialog = document.getElementById("isbnScannerDialog");
  const video = document.getElementById("isbnScannerVideo");
  const cameraSelect = document.getElementById("isbnScannerCamera");
  const scannerStatus = document.getElementById("isbnScannerStatus");
  const scannerError = document.getElementById("isbnScannerError");
  const isbnInput = document.getElementById("isbn");
  const isbnLookupStatus = document.getElementById("isbnLookupStatus");

  if (
    !openButton ||
    !dialog ||
    !video ||
    !cameraSelect ||
    !scannerStatus ||
    !scannerError ||
    !isbnInput
  ) {
    return;
  }

  let scannerControls = null;
  let sessionId = 0;

  function isValidIsbn13(value) {
    if (!/^97[89]\d{10}$/.test(value)) return false;

    const sum = Array.from(value.slice(0, 12)).reduce(
      (total, digit, index) =>
        total + Number(digit) * (index % 2 === 0 ? 1 : 3),
      0,
    );
    const checkDigit = (10 - (sum % 10)) % 10;

    return checkDigit === Number(value[12]);
  }

  function stopVideoTracks() {
    const stream = video.srcObject;
    stream?.getTracks?.().forEach((track) => track.stop());
    video.pause();
    video.srcObject = null;
  }

  function releaseCamera() {
    scannerControls?.stop();
    scannerControls = null;
    stopVideoTracks();
  }

  function stopScanner() {
    sessionId += 1;
    releaseCamera();
  }

  function showCameraError(error) {
    const messages = {
      NotAllowedError:
        "A permissão para usar a câmera foi negada. Autorize o acesso nas configurações do navegador.",
      PermissionDeniedError:
        "A permissão para usar a câmera foi negada. Autorize o acesso nas configurações do navegador.",
      NotFoundError: "Nenhuma câmera foi encontrada neste dispositivo.",
      DevicesNotFoundError: "Nenhuma câmera foi encontrada neste dispositivo.",
      NotReadableError:
        "A câmera não está disponível. Verifique se outro aplicativo está usando o dispositivo.",
      TrackStartError:
        "A câmera não está disponível. Verifique se outro aplicativo está usando o dispositivo.",
      noCamera: "Nenhuma câmera foi encontrada neste dispositivo.",
      insecureContext:
        "A câmera exige HTTPS. No celular, o endereço IP local por HTTP não é seguro; configure HTTPS no servidor.",
      missingLibrary:
        "Não foi possível carregar o leitor de códigos. Recarregue a página e tente novamente.",
    };

    scannerError.textContent =
      messages[error?.name] ||
      "Não foi possível acessar a câmera. Verifique as permissões e tente novamente.";
    scannerError.hidden = false;
    scannerStatus.hidden = true;
  }

  async function startCamera(deviceId) {
    releaseCamera();
    const currentSession = ++sessionId;
    const codeReader = new ZXingBrowser.BrowserMultiFormatReader();
    codeReader.possibleFormats = [ZXingBrowser.BarcodeFormat.EAN_13];
    scannerStatus.textContent =
      "Aponte a câmera para o código de barras EAN-13.";
    scannerStatus.hidden = false;
    scannerError.hidden = true;

    try {
      const controls = await codeReader.decodeFromVideoDevice(
        deviceId || undefined,
        video,
        (result) => {
          if (!result || currentSession !== sessionId || !dialog.open) return;

          const value = result.getText();
          if (result.getBarcodeFormat() !== ZXingBrowser.BarcodeFormat.EAN_13) {
            return;
          }

          if (!isValidIsbn13(value)) {
            scannerStatus.textContent =
              "O código lido não é um ISBN-13 válido. Tente novamente.";
            return;
          }

          isbnInput.value = value;
          isbnInput.dispatchEvent(new Event("input", { bubbles: true }));
          if (isbnLookupStatus) {
            isbnLookupStatus.textContent =
              "ISBN-13 escaneado. Confira o número antes de continuar.";
            isbnLookupStatus.className = "isbn-lookup-status is-success mt-2";
          }
          dialog.close();
        },
      );

      if (currentSession !== sessionId || !dialog.open) {
        controls.stop();
        return;
      }

      scannerControls = controls;
    } catch (error) {
      if (currentSession === sessionId && dialog.open) {
        stopScanner();
        showCameraError(error);
      }
    }
  }

  async function openScanner() {
    if (dialog.open) return;

    dialog.showModal();
    scannerError.hidden = true;
    scannerStatus.hidden = false;
    scannerStatus.textContent = "Solicitando acesso à câmera...";

    if (!window.isSecureContext) {
      showCameraError({ name: "insecureContext" });
      return;
    }

    if (!window.ZXingBrowser) {
      showCameraError({ name: "missingLibrary" });
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      showCameraError({ name: "insecureContext" });
      return;
    }

    const currentSession = ++sessionId;
    let permissionStream;

    try {
      permissionStream = await navigator.mediaDevices.getUserMedia({
        video: true,
      });

      if (currentSession !== sessionId || !dialog.open) return;

      permissionStream.getTracks().forEach((track) => track.stop());
      permissionStream = null;

      const devices =
        await ZXingBrowser.BrowserCodeReader.listVideoInputDevices();
      if (currentSession !== sessionId || !dialog.open) return;
      if (devices.length === 0) {
        throw new DOMException("No camera available.", "noCamera");
      }

      cameraSelect.replaceChildren();
      devices.forEach((device, index) => {
        const option = document.createElement("option");
        option.value = device.deviceId;
        option.textContent = device.label || `Câmera ${index + 1}`;
        cameraSelect.append(option);
      });
      cameraSelect.hidden = devices.length < 2;

      const preferredCamera =
        devices.find((device) =>
          /back|rear|environment|traseira/i.test(device.label),
        ) || devices[0];
      cameraSelect.value = preferredCamera.deviceId;
      await startCamera(preferredCamera.deviceId);
    } catch (error) {
      if (currentSession === sessionId && dialog.open) {
        stopScanner();
        showCameraError(error);
      }
    } finally {
      permissionStream?.getTracks().forEach((track) => track.stop());
    }
  }

  openButton.addEventListener("click", openScanner);
  cameraSelect.addEventListener("change", () => {
    if (dialog.open && cameraSelect.value) {
      void startCamera(cameraSelect.value);
    }
  });
  dialog.querySelectorAll("[data-close-isbn-scanner]").forEach((button) => {
    button.addEventListener("click", () => dialog.close());
  });
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener("close", stopScanner);
})();
