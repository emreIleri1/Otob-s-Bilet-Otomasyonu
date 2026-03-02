<?php
/**
 * Ortak Footer
 */
?>
    </div>
    
    <footer style="background: #ffffff; border-top: 1px solid #e1e4e8; margin-top: 50px; padding: 40px 0 20px;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 0 30px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px; margin-bottom: 30px;">
                <div>
                    <h4 style="color: #333; margin-bottom: 15px; font-size: 18px;">Oto<span style="color: #e94560;">Bilet</span></h4>
                    <p style="color: #666; font-size: 13px; line-height: 1.6;">
                        Türkiye'nin en güvenilir otobüs bilet satış platformu. Hızlı, güvenli ve kolay bilet alımı.
                    </p>
                </div>
                <div>
                    <h5 style="color: #333; margin-bottom: 15px; font-size: 14px;">Hızlı Linkler</h5>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 8px;"><a href="/bym301_project/" style="color: #666; text-decoration: none; font-size: 13px; transition: color 0.3s;" onmouseover="this.style.color='#e94560'" onmouseout="this.style.color='#666'">Ana Sayfa</a></li>
                        <li style="margin-bottom: 8px;"><a href="/bym301_project/passenger/search.php" style="color: #666; text-decoration: none; font-size: 13px; transition: color 0.3s;" onmouseover="this.style.color='#e94560'" onmouseout="this.style.color='#666'">Bilet Ara</a></li>
                        <li style="margin-bottom: 8px;"><a href="/bym301_project/auth/login.php" style="color: #666; text-decoration: none; font-size: 13px; transition: color 0.3s;" onmouseover="this.style.color='#e94560'" onmouseout="this.style.color='#666'">Giriş Yap</a></li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: #333; margin-bottom: 15px; font-size: 14px;">İletişim</h5>
                    <ul style="list-style: none; padding: 0; margin: 0; color: #666; font-size: 13px;">
                        <li style="margin-bottom: 8px;">📞 444 0 123</li>
                        <li style="margin-bottom: 8px;">📧 info@otobilet.com</li>
                        <li style="margin-bottom: 8px;">📍 İstanbul, Türkiye</li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: #333; margin-bottom: 15px; font-size: 14px;">Çalışma Saatleri</h5>
                    <p style="color: #666; font-size: 13px; line-height: 1.8;">
                        Pazartesi - Cuma: 09:00 - 18:00<br>
                        Cumartesi: 10:00 - 16:00<br>
                        Pazar: Kapalı
                    </p>
                </div>
            </div>
            <div style="border-top: 1px solid #e1e4e8; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <p style="color: #888; font-size: 13px; margin: 0;">
                    &copy; <?php echo date('Y'); ?> OtoBilet - Tüm hakları saklıdır.
                </p>
                <p style="color: #888; font-size: 13px; margin: 0;">
                    BYM301 Veritabanı Yönetim Sistemleri Projesi
                </p>
            </div>
        </div>
    </footer>
    
    <script>
        // Navbar aktif sayfa işaretleme
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            document.querySelectorAll('.navbar-nav a').forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>

