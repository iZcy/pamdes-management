<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAMDes - Pemberdayaan Masyarakat Desa</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
      color: white;
      padding: 40px 30px;
      text-align: center;
    }

    .header h1 {
      font-size: 2.5rem;
      margin-bottom: 10px;
      font-weight: 300;
    }

    .header p {
      font-size: 1.1rem;
      opacity: 0.9;
    }

    .nav {
      background: #34495e;
      padding: 15px 30px;
      text-align: right;
    }

    .nav a {
      color: white;
      text-decoration: none;
      margin-left: 20px;
      padding: 8px 16px;
      border-radius: 5px;
      transition: background 0.3s;
    }

    .nav a:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .main-content {
      padding: 40px 30px;
    }

    .intro {
      text-align: center;
      margin-bottom: 40px;
    }

    .intro h2 {
      color: #2c3e50;
      margin-bottom: 15px;
      font-size: 1.8rem;
    }

    .intro p {
      font-size: 1.1rem;
      color: #666;
      max-width: 600px;
      margin: 0 auto;
    }

    .features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 30px;
      margin-bottom: 40px;
    }

    .feature-card {
      background: #f8f9fa;
      padding: 25px;
      border-radius: 10px;
      text-align: center;
      border-left: 4px solid #667eea;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .feature-icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
      color: #667eea;
    }

    .feature-card h3 {
      color: #2c3e50;
      margin-bottom: 10px;
      font-size: 1.3rem;
    }

    .feature-card p {
      color: #666;
      font-size: 0.95rem;
    }

    .cta {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      text-align: center;
    }

    .cta h3 {
      margin-bottom: 15px;
      font-size: 1.5rem;
    }

    .cta-button {
      display: inline-block;
      background: white;
      color: #667eea;
      padding: 12px 30px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      margin-top: 15px;
      transition: transform 0.3s;
    }

    .cta-button:hover {
      transform: scale(1.05);
    }

    .footer {
      background: #2c3e50;
      color: white;
      text-align: center;
      padding: 20px;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      .header h1 {
        font-size: 2rem;
      }

      .nav {
        text-align: center;
      }

      .nav a {
        display: block;
        margin: 5px 0;
      }

      .main-content {
        padding: 20px 15px;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <header class="header">
      <h1>PAMDes</h1>
      <p>Sistem Informasi Perusahaan Air Minum Desa</p>
    </header>

    <nav class="nav">
      <a href="#beranda">Beranda</a>
      <a href="#login">Masuk</a>
      <a href="#daftar">Daftar</a>
    </nav>

    <main class="main-content">
      <section class="intro">
        <h2>Selamat Datang di PAMDes</h2>
        <p>Platform digital untuk pengelolaan Perusahaan Air Minum Desa yang membantu dalam administrasi, pembayaran,
          dan pelayanan air bersih untuk masyarakat desa.</p>
      </section>

      <section class="features">
        <div class="feature-card">
          <div class="feature-icon">💧</div>
          <h3>Manajemen Pelanggan</h3>
          <p>Kelola data pelanggan, meteran air, dan riwayat pemakaian air dengan sistem yang terintegrasi.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">💰</div>
          <h3>Pembayaran & Tagihan</h3>
          <p>Sistem pembayaran digital dan pengelolaan tagihan air yang transparan dan mudah diakses.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>Laporan Operasional</h3>
          <p>Dashboard analitik untuk memantau kinerja operasional, keuangan, dan distribusi air desa.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">🔧</div>
          <h3>Maintenance & Service</h3>
          <p>Jadwal perawatan infrastruktur air dan penanganan keluhan pelanggan secara efisien.</p>
        </div>
      </section>

      <section class="cta">
        <h3>Mulai Kelola PAMDes Anda</h3>
        <p>Bergabunglah dengan sistem PAMDes untuk pengelolaan air minum desa yang lebih efisien dan profesional</p>
        <a href="#mulai" class="cta-button">Mulai Sekarang</a>
      </section>
    </main>

    <footer class="footer">
      <p>&copy; 2025 PAMDes - Perusahaan Air Minum Desa. Solusi digital untuk pengelolaan air bersih desa.</p>
    </footer>
  </div>
</body>

</html>
