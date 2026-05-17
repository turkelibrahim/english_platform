function setTheme(theme){
  document.documentElement.setAttribute('data-theme', theme);
}

async function toggleTheme(){
  const cur = document.documentElement.getAttribute('data-theme') || 'light';
  const next = cur === 'dark' ? 'light' : 'dark';
  setTheme(next);

  // logged in ise backend'e yaz
  try{
    await fetch(`${BASE_URL}/student/api/toggle_theme.php`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`theme=${encodeURIComponent(next)}`
    });
  }catch(e){}
}
