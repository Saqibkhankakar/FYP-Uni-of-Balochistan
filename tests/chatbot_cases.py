"""HTTP chatbot checks run inside the existing disposable-fixture suite."""
import json, re, urllib.parse

def run_chatbot_cases(env):
    public, admin, reception = env['public'], env['admin'], env['reception']
    Page, check, prefix, doctor = env['Page'], env['check'], env['prefix'], env['doctor']
    base = urllib.parse.urlsplit(env['args'].url).path.rstrip('/')
    _, body, _, _ = public.get('/')
    csrf = Page(body).value('csrf')
    check('id="hospital-chat"' in body and 'chatbot.js' in body, 'Public homepage includes local chatbot widget')
    check('id="hospital-chat"' not in admin.get('/admin/index.php')[1], 'Staff pages do not include patient widget')
    check('/login.php' in public.get('/admin/chatbot.php')[2], 'Anonymous user cannot manage knowledge bank')
    check(reception.get('/admin/chatbot.php')[0] == 403, 'Receptionist cannot view knowledge bank')
    check(reception.post('/admin/chatbot.php', {'id':'1'})[0] == 403, 'Receptionist cannot submit knowledge edits')
    check(admin.post('/admin/chatbot.php', {'question_en':'bad'})[0] == 419, 'Knowledge editor enforces CSRF')
    admin_csrf=Page(admin.get('/admin/chatbot.php')[1]).value('csrf')
    code, invalid_body, _, _=admin.post('/admin/chatbot.php', {'csrf':admin_csrf,'question_en[]':'invalid array'})
    check(code==200 and 'Fill both language questions' in invalid_body, 'Malformed admin fields show validation instead of a server error')
    check(public.get('/chatbot.php')[0] == 405, 'Chat API rejects GET')
    check(public.post('/chatbot.php', {'message':'headache'})[0] == 403, 'Chat API requires session CSRF')
    check(public.post('/chatbot.php', {'csrf':csrf,'message':'a'*501})[0] == 422, 'Chat API rejects oversized input')
    check(public.post('/chatbot.php', {'csrf':csrf,'message[]':'headache'})[0] == 422, 'Chat API rejects non-string input')
    def ask(text):
        code, body, _, headers = public.post('/chatbot.php', {'csrf':csrf,'message':text})
        return code, json.loads(body), headers
    code, result, headers = ask('I have a headache')
    check(code == 200 and result.get('specialty') == 'General Medicine' and 'application/json' in headers.get('Content-Type','') and headers.get('Cache-Control') == 'no-store', 'Common question returns private JSON routing response')
    check(ask('Mere pait mein dard hai')[1].get('specialty') == 'Gastroenterology', 'Roman Urdu HTTP question recognized')
    check(ask('میرے پیٹ میں درد ہے')[1].get('specialty') == 'Gastroenterology', 'Urdu HTTP question recognized')
    check(ask('toothace')[1].get('specialty') == 'Dentistry', 'Misspelled HTTP question recognized')
    check(ask('unrelated spaceship wallpaper')[1]['type'] == 'fallback', 'Unknown HTTP question gets safe fallback')
    check(ask('rash and toothache')[1]['type'] == 'clarify', 'Ambiguous HTTP query asks clarification')
    check(ask('when available')[1]['type'] == 'fallback', 'Ambiguity clears old context')
    # Only the existing QA doctor is changed; the suite removes it in its finally block.
    _, body, _, _ = admin.get('/admin/doctors.php')
    form = {'csrf':Page(body).value('csrf'),'id':doctor,'name':prefix+' Doctor','specialization':'Dermatologist','qualifications':'MBBS','phone':'03001234567','active':'1'}
    admin.post('/admin/doctors.php', form)
    code, result, _ = ask('acne')
    card = next((d for d in result['doctors'] if d['id'] == doctor),None)
    check(card is not None and card['name'] == prefix+' Doctor', 'Live doctor specialization appears in chatbot cards')
    check(card['booking_url'] == base+'/book.php?doctor='+str(doctor), 'Chatbot booking link uses existing patient workflow')
    check(ask('when available')[1].get('specialty') == 'Dermatology', 'HTTP follow-up remembers routing category')
    public.post('/chatbot.php', {'csrf':csrf,'action':'reset'})
    check(ask('when available')[1]['type'] == 'fallback', 'Clear chat removes server context')
    _, body, _, _ = admin.get('/admin/chatbot.php')
    entry = {'csrf':Page(body).value('csrf'),'question_en':prefix+' routing','question_ur':'آزمائشی رہنمائی','patterns':'uniqueroutingword','specialty':'Dermatology','answer_en':'Safe test routing <script>alert(1)</script>','answer_ur':'متعلقہ ڈاکٹر سے رابطہ کریں۔','active':'1'}
    code, body, _, _ = admin.post('/admin/chatbot.php', entry)
    check(code == 200 and prefix+' routing' in body and '&lt;script&gt;' in body and '<script>alert(1)</script>' not in body, 'Admin creates entry and editor escapes stored content')
    row=next(r for r in re.findall(r'<tr>.*?</tr>',body,re.S) if prefix+' routing' in r)
    entry['id']=re.search(r'\?edit=(\d+)',row).group(1)
    check(prefix+' routing' in admin.get('/admin/chatbot.php?q='+prefix)[1], 'Knowledge bank search finds new entry')
    check(ask('uniqueroutingword')[1]['message'] == entry['answer_en'], 'New knowledge entry immediately answers requests as plain text')
    entry.pop('active'); admin.post('/admin/chatbot.php', entry)
    check(ask('uniqueroutingword')[1]['type'] == 'fallback', 'Disabling knowledge entry takes effect immediately')
    public.post('/language.php',{'csrf':csrf,'language':'ur','return':base+'/'})
    code,result,_=ask('جلد پر دانے ہیں')
    check(code==200 and 'جلد' in result['message'] and result['disclaimer'].startswith('یہ معاون'), 'Urdu session produces Urdu answer and disclaimer')
    public.post('/language.php',{'csrf':csrf,'language':'en','return':base+'/'})
    # Consume the remaining per-IP budget and verify the actual limit, not a mock.
    last=0
    for _ in range(22):
        last=ask('hello')[0]
        if last==429: break
    check(last==429, 'Chat API enforces 20 requests per minute')
    code,result,_=ask('severe chest pain')
    check(code==200 and result['type']=='emergency' and not result['doctors'], 'Urgent guidance bypasses exhausted ordinary-chat rate limit')
