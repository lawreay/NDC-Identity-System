import json
import os
import shutil

root = os.path.dirname(os.path.abspath(__file__))
storage = os.path.join(root, 'templates')
source = os.path.join(storage, 'template_1')
if not os.path.isdir(source):
    raise SystemExit('Source template_1 not found')

entries = [
    {
        'id': 'template_4',
        'name': 'Clean Academic',
        'description': 'A white academic card with top branding and a classic layout.',
        'front_html': '''<div style="width:100%;height:100%;padding:14px;background-image:url('{{template.front_background}}');background-size:cover;background-position:center;color:#14213d;font-family:Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.92);border-radius:16px;padding:18px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div>
        <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#fca311;">{{organization.name}}</div>
        <div style="font-size:16px;font-weight:700;color:#14213d;">Student Identity Card</div>
      </div>
      <img src="{{organization.logo}}" alt="Logo" style="max-height:50px;max-width:120px;object-fit:contain;">
    </div>
    <div style="display:flex;gap:16px;">
      <div style="width:120px;height:150px;border:3px solid #14213d;border-radius:14px;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:10px;">
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#667;">Name</div>
          <div style="font-size:20px;font-weight:700;color:#14213d;">{{student.full_name}}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:11px;color:#334;">
          <div><div style="font-size:8px;text-transform:uppercase;color:#667;">Student ID</div>{{student.student_id}}</div>
          <div><div style="font-size:8px;text-transform:uppercase;color:#667;">Program</div>{{student.program}}</div>
          <div><div style="font-size:8px;text-transform:uppercase;color:#667;">Department</div>{{student.department}}</div>
          <div><div style="font-size:8px;text-transform:uppercase;color:#667;">Status</div>{{student.status}}</div>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid #e5e5e5;">
      <div style="font-size:10px;color:#667;">Valid: {{student.issue_date}} – {{student.expiry_date}}</div>
      <div style="font-size:10px;color:#14213d;font-weight:700;">{{organization.website}}</div>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;padding:16px;background-image:url('{{template.back_background}}');background-size:cover;background-position:center;color:#14213d;font-family:Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.94);border-radius:16px;padding:18px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="font-size:12px;font-weight:700;color:#14213d;margin-bottom:10px;">Card Details</div>
      <div style="font-size:11px;line-height:1.6;color:#333;">
        This card is the property of {{organization.name}} and must be returned when requested. If found, please contact the issuer or use the phone number provided.
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:10px;color:#334;">
      <div><strong>Office</strong><br>{{organization.address}}</div>
      <div><strong>Contact</strong><br>{{organization.phone}}<br>{{organization.email}}</div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;">
      <div style="font-size:9px;color:#667;">
        Authorized by<br><strong>{{authorized.name}}</strong>
      </div>
      <div style="text-align:right;">
        <div style="font-size:9px;color:#667;">Verification</div>
        <div>{{card.barcode}}</div>
      </div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_5',
        'name': 'Executive Badge',
        'description': 'A high-contrast executive style ID with a left color bar and clean data blocks.',
        'front_html': '''<div style="width:100%;height:100%;position:relative;background:#070a52;color:#fff;font-family:Verdana,sans-serif;overflow:hidden;">
  <div style="position:absolute;left:0;top:0;bottom:0;width:88px;background:{{theme.primary_color}};"></div>
  <div style="position:absolute;right:0;top:0;bottom:0;width:220px;background:rgba(255,255,255,0.08);"></div>
  <div style="position:absolute;left:104px;top:18px;right:18px;bottom:18px;padding:18px;box-sizing:border-box;display:grid;grid-template-columns:120px 1fr;gap:16px;">
    <div style="border:2px solid rgba(255,255,255,0.6);border-radius:14px;overflow:hidden;background:#fff;">
      <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;min-height:180px;">
    </div>
    <div style="display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.7);">{{organization.name}}</div>
        <div style="font-size:24px;font-weight:700;margin:10px 0;">{{student.full_name}}</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.8);">{{student.program}}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:10px;color:#cbd5e1;">
        <div style="background:rgba(255,255,255,0.08);padding:10px;border-radius:10px;"><strong>ID</strong><br>{{student.student_id}}</div>
        <div style="background:rgba(255,255,255,0.08);padding:10px;border-radius:10px;"><strong>Status</strong><br>{{student.status}}</div>
      </div>
    </div>
  </div>
  <div style="position:absolute;left:104px;bottom:18px;right:18px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.12);display:flex;justify-content:space-between;font-size:9px;color:rgba(255,255,255,0.65);">
    <span>{{organization.website}}</span>
    <span>Valid until {{student.expiry_date}}</span>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#101540;color:#fff;font-family:Verdana,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:{{theme.primary_color}};font-weight:700;margin-bottom:8px;">Access Credentials</div>
  <div style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);border-radius:16px;padding:16px;display:grid;grid-template-columns:1fr auto;gap:14px;min-height:220px;">
    <div style="display:grid;gap:10px;font-size:11px;line-height:1.6;color:#e9ebff;">
      <div><strong>Name</strong><br>{{student.full_name}}</div>
      <div><strong>ID Number</strong><br>{{student.student_id}}</div>
      <div><strong>Program</strong><br>{{student.program}}</div>
      <div><strong>Department</strong><br>{{student.department}}</div>
      <div><strong>Issued</strong><br>{{student.issue_date}}</div>
      <div><strong>Expires</strong><br>{{student.expiry_date}}</div>
    </div>
    <div style="display:flex;flex-direction:column;justify-content:space-between;align-items:center;">
      <div>{{card.qr_code}}</div>
      <div style="font-size:9px;color:rgba(255,255,255,0.7);">Scan for validation</div>
    </div>
  </div>
  <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:9px;color:rgba(255,255,255,0.7);">
    <div>
      Authorized by<br><strong>{{authorized.name}}</strong>
    </div>
    <div>{{organization.phone}}</div>
  </div>
</div>''',
    },
    {
        'id': 'template_6',
        'name': 'Minimal Contrast',
        'description': 'A soft minimalist template with centered details and subtle separators.',
        'front_html': '''<div style="width:100%;height:100%;padding:18px;background-image:url('{{template.front_background}}');background-size:cover;background-position:center;color:#1f2937;font-family:Helvetica,Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.92);border-radius:18px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
    <div style="display:flex;justify-content:center;align-items:center;gap:12px;">
      <div style="width:68px;height:68px;border-radius:18px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;"><img src="{{organization.logo}}" alt="Logo" style="max-width:48px;max-height:48px;object-fit:contain;"></div>
      <div style="text-align:center;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;">{{organization.name}}</div>
        <div style="font-size:14px;font-weight:700;color:#111827;">Student Card</div>
      </div>
    </div>
    <div style="display:flex;justify-content:center;">
      <div style="width:138px;height:168px;border-radius:18px;overflow:hidden;border:2px solid #d1d5db;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
      </div>
    </div>
    <div style="display:grid;gap:6px;text-align:center;">
      <div style="font-size:16px;font-weight:700;color:#111827;">{{student.full_name}}</div>
      <div style="font-size:11px;color:#6b7280;">{{student.program}} · {{student.department}}</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:10px;color:#333;">
        <div><strong>ID</strong><br>{{student.student_id}}</div>
        <div><strong>Expires</strong><br>{{student.expiry_date}}</div>
      </div>
    </div>
    <div style="display:flex;justify-content:center;gap:12px;font-size:10px;color:#6b7280;">
      <span>{{organization.phone}}</span>
      <span>{{organization.email}}</span>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;padding:16px;background:#f3f4f6;color:#111827;font-family:Helvetica,Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.96);border-radius:18px;padding:18px;display:grid;grid-template-rows:auto 1fr auto;gap:12px;box-sizing:border-box;">
    <div style="font-size:12px;font-weight:700;color:#111827;">Card Information</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:10px;color:#374151;">
      <div style="background:#f8fafc;padding:12px;border-radius:14px;">
        <div style="font-size:9px;text-transform:uppercase;color:#6b7280;">Program</div>
        <div>{{student.program}}</div>
      </div>
      <div style="background:#f8fafc;padding:12px;border-radius:14px;">
        <div style="font-size:9px;text-transform:uppercase;color:#6b7280;">Department</div>
        <div>{{student.department}}</div>
      </div>
      <div style="background:#f8fafc;padding:12px;border-radius:14px;">
        <div style="font-size:9px;text-transform:uppercase;color:#6b7280;">Issue Date</div>
        <div>{{student.issue_date}}</div>
      </div>
      <div style="background:#f8fafc;padding:12px;border-radius:14px;">
        <div style="font-size:9px;text-transform:uppercase;color:#6b7280;">Status</div>
        <div>{{student.status}}</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <div style="font-size:9px;color:#6b7280;">
        Authorized by<br><strong>{{authorized.name}}</strong>
      </div>
      <div style="text-align:right;">
        <div style="font-size:9px;color:#6b7280;">Verify</div>
        {{card.qr_code}}
      </div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_7',
        'name': 'Bold Stripe',
        'description': 'A layout with a bold color stripe and contrasting data panels.',
        'front_html': '''<div style="width:100%;height:100%;background:#fff;font-family:Arial,sans-serif;position:relative;overflow:hidden;">
  <div style="position:absolute;left:0;top:0;bottom:0;width:110px;background:{{theme.accent_color}};"></div>
  <div style="position:absolute;top:22px;left:126px;right:18px;bottom:22px;padding:18px;">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#4b5563;">{{organization.name}}</div>
    <div style="font-size:22px;font-weight:700;color:#111827;margin-top:6px;">{{student.full_name}}</div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:14px;margin-top:20px;">
      <div style="background:#f8fafc;padding:16px;border-radius:16px;display:grid;gap:10px;font-size:11px;color:#334155;">
        <div><strong>ID</strong> {{student.student_id}}</div>
        <div><strong>Program</strong> {{student.program}}</div>
        <div><strong>Department</strong> {{student.department}}</div>
        <div><strong>Status</strong> {{student.status}}</div>
      </div>
      <div style="width:140px;height:180px;border-radius:18px;overflow:hidden;border:2px solid #e2e8f0;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
      </div>
    </div>
    <div style="margin-top:18px;display:flex;justify-content:space-between;align-items:center;font-size:10px;color:#6b7280;">
      <span>Expires {{student.expiry_date}}</span>
      <span>Issued {{student.issue_date}}</span>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#f8fafc;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;background:#fff;border-radius:18px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="font-size:12px;font-weight:700;color:#111827;margin-bottom:10px;">Card Notes</div>
      <div style="font-size:10px;line-height:1.6;color:#475569;">
        This card is issued subject to the rules of {{organization.name}}. It is non-transferable and must be surrendered on request.
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;">
      <div style="font-size:10px;color:#334155;">
        <div><strong>Issuer</strong></div>
        <div>{{organization.name}}</div>
        <div>{{organization.address}}</div>
      </div>
      <div style="text-align:center;">
        {{card.qr_code}}
        <div style="font-size:9px;color:#6b7280;margin-top:6px;">Scan code</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:9px;color:#475569;">
      <div>
        Authorized by<br><strong>{{authorized.name}}</strong>
      </div>
      <div>{{organization.website}}</div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_8',
        'name': 'Gradient Edge',
        'description': 'A soft gradient edge design with elegant content panels and signature area.',
        'front_html': '''<div style="width:100%;height:100%;padding:16px;background:linear-gradient(135deg, #ffffff 0%, #eef2ff 100%);font-family:Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.98);border-radius:20px;box-shadow:0 20px 40px rgba(15,23,42,0.08);padding:18px;display:grid;grid-template-columns:140px 1fr;gap:18px;overflow:hidden;">
    <div style="border-radius:18px;overflow:hidden;border:1px solid #c7d2fe;">
      <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;min-height:220px;">
    </div>
    <div style="display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#667eea;">{{organization.name}}</div>
        <div style="font-size:22px;font-weight:700;margin:10px 0;color:#1e293b;">{{student.full_name}}</div>
        <div style="font-size:12px;color:#334155;">{{student.program}}</div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;font-size:10px;color:#475569;">
        <div style="background:#f8fafc;padding:12px;border-radius:14px;"><div style="font-weight:700;">ID</div>{{student.student_id}}</div>
        <div style="background:#f8fafc;padding:12px;border-radius:14px;"><div style="font-weight:700;">Status</div>{{student.status}}</div>
      </div>
      <div style="font-size:10px;color:#6b7280;display:flex;justify-content:space-between;">
        <span>Valid until {{student.expiry_date}}</span>
        <span>Issued {{student.issue_date}}</span>
      </div>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;padding:18px;background:linear-gradient(135deg,#eef2ff 0%,#ffffff 100%);font-family:Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:rgba(255,255,255,0.96);border-radius:20px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="font-size:12px;font-weight:700;color:#4338ca;margin-bottom:8px;">Important Information</div>
      <div style="font-size:10px;line-height:1.6;color:#334155;">
        This card grants access to campus facilities and services for the holder. Report lost cards immediately to administration.
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;">
      <div style="font-size:10px;color:#475569;">
        <div><strong>Organization</strong></div>
        <div>{{organization.name}}</div>
        <div>{{organization.website}}</div>
      </div>
      <div style="text-align:center;">
        {{card.qr_code}}
        <div style="font-size:9px;color:#94a3b8;margin-top:4px;">Scan to verify</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <div style="font-size:10px;color:#475569;">
        Authorized by<br><strong>{{authorized.name}}</strong>
      </div>
      <div style="font-size:9px;color:#94a3b8;">{{organization.phone}}</div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_9',
        'name': 'Dark Mode',
        'description': 'A dark elegant ID card with a strong contrast and modern typography.',
        'front_html': '''<div style="width:100%;height:100%;background:#111827;color:#f8fafc;font-family:'Segoe UI',sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;border-radius:20px;background:linear-gradient(180deg,rgba(30,41,59,0.98),rgba(15,23,42,0.98));padding:18px;display:grid;grid-template-columns:150px 1fr;gap:18px;overflow:hidden;">
    <div style="border-radius:20px;overflow:hidden;border:2px solid rgba(255,255,255,0.1);">
      <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;min-height:230px;">
    </div>
    <div style="display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;">{{organization.name}}</div>
        <div style="font-size:24px;font-weight:700;margin:10px 0;color:#f8fafc;">{{student.full_name}}</div>
        <div style="font-size:11px;color:#cbd5e1;">{{student.program}}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:10px;color:#cbd5e1;">
        <div style="background:rgba(255,255,255,0.06);padding:12px;border-radius:14px;"><strong>ID</strong><br>{{student.student_id}}</div>
        <div style="background:rgba(255,255,255,0.06);padding:12px;border-radius:14px;"><strong>Valid</strong><br>{{student.expiry_date}}</div>
      </div>
    </div>
  </div>
  <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:9px;color:#94a3b8;">
    <span>Issued {{student.issue_date}}</span>
    <span>{{organization.website}}</span>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#0f172a;color:#e2e8f0;font-family:'Segoe UI',sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;border-radius:20px;background:rgba(15,23,42,0.95);padding:18px;display:grid;grid-template-rows:auto 1fr auto;gap:14px;">
    <div style="font-size:12px;font-weight:700;color:#f8fafc;">Card Authentication</div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;">
      <div style="display:grid;gap:10px;font-size:10px;color:#cbd5e1;">
        <div><strong>Organization</strong><br>{{organization.name}}</div>
        <div><strong>Address</strong><br>{{organization.address}}</div>
        <div><strong>Phone</strong><br>{{organization.phone}}</div>
        <div><strong>Email</strong><br>{{organization.email}}</div>
      </div>
      <div style="text-align:center;">
        {{card.qr_code}}
        <div style="font-size:9px;color:#94a3b8;margin-top:6px;">Secure ID</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:#94a3b8;">
      <div>
        {{authorized.name}}<br><span style="color:#cbd5e1;">Authorized</span>
      </div>
      <div>{{organization.website}}</div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_10',
        'name': 'Soft Pastel',
        'description': 'A soft pastel card with rounded panels and gentle text styling.',
        'front_html': '''<div style="width:100%;height:100%;padding:18px;background:#f7f6ff;font-family:Arial,sans-serif;box-sizing:border-box;">
  <div style="width:100%;height:100%;background:linear-gradient(180deg,#ffffff 0%,#eef2ff 100%);border-radius:24px;display:grid;grid-template-columns:1fr 210px;gap:18px;padding:18px;box-sizing:border-box;overflow:hidden;">
    <div style="display:flex;flex-direction:column;justify-content:space-between;gap:12px;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#7c3aed;">{{organization.name}}</div>
        <div style="font-size:20px;font-weight:700;color:#312e81;margin-top:10px;">{{student.full_name}}</div>
        <div style="font-size:11px;color:#4c51bf;">{{student.program}}</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e0e7ff;border-radius:18px;padding:14px;font-size:11px;color:#3c366b;display:grid;gap:8px;">
        <div><strong>ID</strong><br>{{student.student_id}}</div>
        <div><strong>Dept</strong><br>{{student.department}}</div>
        <div><strong>Expires</strong><br>{{student.expiry_date}}</div>
      </div>
      <div style="font-size:10px;color:#7c3aed;">{{organization.website}}</div>
    </div>
    <div style="border-radius:20px;overflow:hidden;border:1px solid #ddd;background:#fff;display:flex;align-items:center;justify-content:center;">
      <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;min-height:240px;">
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#faf5ff;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;background:#ffffff;border-radius:24px;padding:18px;box-sizing:border-box;display:grid;grid-template-rows:auto 1fr auto;gap:14px;">
    <div style="font-size:12px;font-weight:700;color:#4c51bf;">Notes & Contact</div>
    <div style="font-size:10px;line-height:1.6;color:#4b4b6d;">
      This card is proof of student identity. Present it when asked for verification, and return it if found.
      <br><br>
      <strong>{{organization.name}}</strong><br>
      {{organization.address}}<br>
      {{organization.phone}}<br>
      {{organization.email}}
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:#7c3aed;">
      <div>
        Authorized by<br><strong>{{authorized.name}}</strong>
      </div>
      <div>{{card.barcode}}</div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_11',
        'name': 'Grid Layout',
        'description': 'A structured grid layout with equal emphasis on photo and institution data.',
        'front_html': '''<div style="width:100%;height:100%;background:#ffffff;font-family:Arial,sans-serif;box-sizing:border-box;padding:16px;">
  <div style="width:100%;height:100%;border-radius:22px;border:1px solid #e2e8f0;display:grid;grid-template-columns:1fr 1fr;overflow:hidden;">
    <div style="background:#eef2ff;padding:18px;display:flex;flex-direction:column;justify-content:space-between;gap:16px;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#334155;">{{organization.name}}</div>
        <div style="font-size:18px;font-weight:700;color:#1e293b;margin-top:6px;">Student Identity</div>
      </div>
      <div style="display:flex;justify-content:center;align-items:center;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;max-width:176px;max-height:212px;object-fit:cover;border-radius:20px;box-shadow:0 10px 20px rgba(15,23,42,0.08);">
      </div>
    </div>
    <div style="padding:18px;display:grid;gap:14px;">
      <div style="font-size:12px;font-weight:700;color:#0f172a;">{{student.full_name}}</div>
      <div style="font-size:10px;color:#475569;">{{student.program}}</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:10px;color:#334155;">
        <div><strong>ID</strong><br>{{student.student_id}}</div>
        <div><strong>Status</strong><br>{{student.status}}</div>
        <div><strong>Department</strong><br>{{student.department}}</div>
        <div><strong>Valid</strong><br>{{student.expiry_date}}</div>
      </div>
      <div style="background:#f8fafc;padding:12px;border-radius:16px;font-size:10px;color:#475569;">
        {{organization.website}}<br>{{organization.phone}}
      </div>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#f8fafc;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;background:#ffffff;border-radius:22px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
    <div style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:10px;">Card Terms</div>
    <div style="font-size:10px;color:#475569;line-height:1.6;">
      This card is issued subject to the rules of {{organization.name}}. It is non-transferable and must be surrendered on request.
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;">
      <div style="font-size:10px;color:#334155;">
        <div><strong>Authorized</strong></div>
        <div>{{authorized.name}}</div>
      </div>
      <div style="text-align:center;">
        {{card.barcode}}
      </div>
    </div>
    <div style="font-size:9px;color:#64748b;display:flex;justify-content:space-between;">
      <span>Issued {{student.issue_date}}</span>
      <span>{{organization.email}}</span>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_12',
        'name': 'Photo First',
        'description': 'A photo-first layout with bold student identity focus and clear metadata blocks.',
        'front_html': '''<div style="width:100%;height:100%;background:#ffffff;font-family:Arial,sans-serif;box-sizing:border-box;padding:16px;">
  <div style="width:100%;height:100%;border-radius:24px;border:1px solid #e5e7eb;display:grid;grid-template-columns:220px 1fr;overflow:hidden;">
    <div style="background:url('{{template.front_background}}') center/cover no-repeat;">
      <div style="width:100%;height:100%;background:rgba(15,23,42,0.6);display:flex;align-items:center;justify-content:center;">
        <img src="{{student.photo}}" alt="Student photo" style="width:180px;height:220px;object-fit:cover;border-radius:24px;border:4px solid #fff;">
      </div>
    </div>
    <div style="padding:22px;display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#64748b;">{{organization.name}}</div>
        <div style="font-size:24px;font-weight:700;color:#111827;margin-top:10px;">{{student.full_name}}</div>
        <div style="font-size:12px;color:#475569;">{{student.program}}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:11px;color:#334155;">
        <div><strong>Student ID</strong><br>{{student.student_id}}</div>
        <div><strong>Status</strong><br>{{student.status}}</div>
        <div><strong>Department</strong><br>{{student.department}}</div>
        <div><strong>Expires</strong><br>{{student.expiry_date}}</div>
      </div>
      <div style="font-size:10px;color:#475569;">{{organization.phone}} · {{organization.email}}</div>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#f8fafc;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;background:#ffffff;border-radius:24px;padding:18px;box-sizing:border-box;display:grid;grid-template-rows:auto 1fr auto;gap:16px;">
    <div style="font-size:12px;font-weight:700;color:#111827;">Terms of Use</div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;font-size:10px;color:#334155;">
      <div style="font-size:10px;color:#334155;">
        <div><strong>Organization</strong></div>
        <div>{{organization.name}}</div>
        <div>{{organization.address}}</div>
        <div>{{organization.phone}}</div>
      </div>
      <div style="text-align:center;">
        {{card.qr_code}}
        <div style="font-size:9px;color:#64748b;margin-top:6px;">Verification</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:#475569;">
      <div>
        {{authorized.name}}<br><span style="color:#64748b;">Authorized Signatory</span>
      </div>
      <div>{{organization.website}}</div>
    </div>
  </div>
</div>''',
    },
    {
        'id': 'template_13',
        'name': 'Security Access',
        'description': 'A secure access card theme with strong labels and a visible validation area.',
        'front_html': '''<div style="width:100%;height:100%;background:#f8fafc;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;background:#ffffff;border-radius:24px;border:1px solid #cbd5e1;padding:18px;display:grid;grid-template-columns:1fr 140px;gap:18px;overflow:hidden;">
    <div style="display:flex;flex-direction:column;justify-content:space-between;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#334155;">{{organization.name}}</div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;margin-top:8px;">{{student.full_name}}</div>
        <div style="font-size:11px;color:#475569;margin-top:6px;">{{student.program}}</div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:10px;color:#334155;">
        <div style="background:#eef2ff;padding:12px;border-radius:16px;"><strong>ID</strong><br>{{student.student_id}}</div>
        <div style="background:#eef2ff;padding:12px;border-radius:16px;"><strong>Expires</strong><br>{{student.expiry_date}}</div>
        <div style="background:#eef2ff;padding:12px;border-radius:16px;"><strong>Dept</strong><br>{{student.department}}</div>
        <div style="background:#eef2ff;padding:12px;border-radius:16px;"><strong>Status</strong><br>{{student.status}}</div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
      <div style="width:120px;height:160px;border-radius:22px;overflow:hidden;border:2px solid #cbd5e1;">
        <img src="{{student.photo}}" alt="Student photo" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <div style="font-size:9px;color:#64748b;text-align:center;">Secure Access</div>
    </div>
  </div>
</div>''',
        'back_html': '''<div style="width:100%;height:100%;background:#ffffff;font-family:Arial,sans-serif;box-sizing:border-box;padding:18px;">
  <div style="width:100%;height:100%;border-radius:24px;border:1px solid #cbd5e1;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
    <div style="font-size:12px;font-weight:700;color:#0f172a;">Authorization</div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;font-size:10px;color:#334155;">
      <div>
        <div><strong>Organization</strong></div>
        <div>{{organization.name}}</div>
        <div>{{organization.address}}</div>
        <div>{{organization.phone}}</div>
      </div>
      <div style="text-align:center;">
        {{card.qr_code}}
        <div style="font-size:9px;color:#64748b;margin-top:6px;">Authorized Check</div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:#475569;">
      <div>
        {{authorized.name}}<br><span style="color:#64748b;">Authorized Signatory</span>
      </div>
      <div>{{organization.website}}</div>
    </div>
  </div>
</div>''',
    }
]

for entry in entries:
    directory = os.path.join(storage, entry['id'])
    os.makedirs(directory, exist_ok=True)
    for asset in ['front-background.png', 'back-background.png', 'thumbnail.png']:
        src = os.path.join(source, asset)
        dst = os.path.join(directory, asset)
        if os.path.isfile(src):
            shutil.copy(src, dst)
    data = {
        'id': entry['id'],
        'name': entry['name'],
        'description': entry['description'],
        'front_html': entry['front_html'],
        'back_html': entry['back_html'],
        'front_background_path': f'storage/templates/{entry["id"]}/front-background.png',
        'back_background_path': f'storage/templates/{entry["id"]}/back-background.png',
        'thumbnail_path': f'storage/templates/{entry["id"]}/thumbnail.png',
        'status': 'active',
        'created_by': 'Administrator',
        'created_at': '2026-08-04 12:00:00',
        'updated_at': '2026-08-04 12:00:00',
    }
    with open(os.path.join(directory, 'template.json'), 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
print('Created', len(entries), 'new templates.')
