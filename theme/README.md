# Tema

`Seviye Storefront` teması bu klasörde yaşayacak. Kapsamı, ürün
spesifikasyonundaki giriş ekranı (yalnızca TC Kimlik No + şifre ile giriş,
giriş yapılmadan ürün görüntülenemez) ve rol bazlı panel yönlendirmesi
(`store.seviye.com.tr`, `/sube`, `/admin`) etrafında kurulacak.

Bu milestone'da yalnızca repo iskeleti oluşturuldu; tema henüz yazılmadı.
Sıradaki aday adım için bkz. `docs/ROADMAP.md` → "Giriş ekranı + TC Kimlik No
auth akışı".

Tema, diğer tüm bileşenler gibi yalnızca Seviye Core'un (ve ilgili modüllerin)
yayınladığı public API'ler / REST uç noktaları üzerinden veri okuyacak;
doğrudan `scp_*` tablolarını sorgulamayacak.
