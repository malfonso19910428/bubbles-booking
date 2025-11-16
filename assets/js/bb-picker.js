// Bubbles Picker JS (auto-init + función pública)
(function(){
  // Debounce
  function debounce(fn,ms){var t;return function(){var a=arguments,ctx=this;clearTimeout(t);t=setTimeout(function(){fn.apply(ctx,a);},ms||220);};}

  function filterContains(arr,q){
    q=(q||"").toLowerCase().trim();
    if(!q) return arr.slice(0,30);
    var starts=arr.filter(function(x){return x.toLowerCase().indexOf(q)===0;});
    var contains=arr.filter(function(x){return x.toLowerCase().indexOf(q)>0;});
    return starts.concat(contains).slice(0,30);
  }

  function mountSuggest(root, input, box, itemsFetcher, nextInput){
    if(!input || !box) return;
    function hideAll(){ root.querySelectorAll(".bb-suggest").forEach(function(el){ el.style.display="none"; }); }
    function pick(val){
      input.value = val;
      box.style.display = "none";
      input.blur();
      if (nextInput && nextInput.focus){ setTimeout(function(){ nextInput.focus(); if(nextInput.select) nextInput.select(); }, 0); }
    }
    var fire = debounce(function(){
      var q=input.value||"";
      itemsFetcher(q, function(list){
        if(!Array.isArray(list)) list=[];
        if(!list.length){ box.style.display="none"; box.innerHTML=""; return; }
        box.innerHTML = list.map(function(x){return '<div class="bb-opt">'+x+'</div>';}).join("");
        box.style.display="block";
        box.querySelectorAll(".bb-opt").forEach(function(el){
          el.addEventListener("click", function(){ hideAll(); pick(el.textContent); });
        });
      });
    },250);
    input.addEventListener("input",fire);
    input.addEventListener("focus",function(){ fire(); });
    input.addEventListener("keydown",function(e){
      if(e.key==="Enter"){
        e.preventDefault();
        var first=box.querySelector(".bb-opt");
        if(first){ hideAll(); pick(first.textContent); }
        else if(nextInput){ hideAll(); input.blur(); setTimeout(function(){ nextInput.focus(); if(nextInput.select) nextInput.select(); },0); }
      } else if(e.key==="Escape"){
        box.style.display="none";
      }
    });
    document.addEventListener("click",function(e){ if(!box.contains(e.target) && e.target!==input){ box.style.display="none"; }});
  }

  // Datos base
  var years=[], maxY=(new Date()).getFullYear()+1;
  for (var y=maxY; y>=1980; --y) years.push(String(y));

  var popularMakes=["Toyota","Tesla","Ford","Chevrolet","Honda","Nissan","Hyundai","Kia","BMW","Mercedes-Benz","Volkswagen","Mazda","Subaru","Audi","Lexus","Jeep","Dodge","GMC","Ram","Cadillac","Acura","Infiniti","Lincoln","Volvo","Porsche","Land Rover","Mini","Mitsubishi","Buick","Chrysler","Jaguar"];
  var popularColors=["Black","White","Silver","Gray","Grey","Blue","Dark Blue","Navy","Red","Maroon","Burgundy","Green","Dark Green","Olive","Beige","Tan","Brown","Gold","Yellow","Orange","Purple","Pink","Pearl","Ivory","Charcoal","Teal","Turquoise","Bronze","Copper","Champagne","Gunmetal","Matte Black","Gloss Black","Metallic Blue"];

  var vpTypes=["car","truck","multipurpose%20passenger%20vehicle%20(MPV)","suv"], vpAllMakes=null;
  function fetchAllMakes(){
    if(vpAllMakes) return Promise.resolve(vpAllMakes);
    var urls=vpTypes.map(function(t){return "https://vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/"+t+"?format=json";});
    return Promise.all(urls.map(function(u){return fetch(u,{mode:"cors",credentials:"omit"}).then(function(r){return r.json();}).catch(function(){return {Results:[]};});}))
      .then(function(all){
        var set={}; all.forEach(function(js){(js.Results||[]).forEach(function(r){ if(r&&r.MakeName) set[r.MakeName.trim()]=1; });});
        vpAllMakes=Object.keys(set).sort(function(a,b){return a.localeCompare(b,undefined,{sensitivity:"accent",numeric:true});});
        return vpAllMakes;
      });
  }
  function rankMakes(list,q){
    q=(q||"").toLowerCase().trim();
    if(!q) return popularMakes.slice(0,40);
    var exact=[],starts=[],contains=[];
    list.forEach(function(m){var ml=m.toLowerCase(); if(ml.indexOf(q)===-1) return; if(ml===q) exact.push(m); else if(ml.indexOf(q)===0) starts.push(m); else contains.push(m);});
    return exact.concat(starts,contains).slice(0,50);
  }
  function fetchModels(make,year){
    if(!make||!year) return Promise.resolve([]);
    var url="https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/"+encodeURIComponent(make)+"/modelyear/"+encodeURIComponent(year)+"?format=json";
    return fetch(url,{mode:"cors",credentials:"omit"})
      .then(function(r){return r.json();})
      .then(function(js){
        var arr=[];(js.Results||[]).forEach(function(r){var n=r.Model_Name||r.ModelName; if(n) arr.push(n.trim());});
        return Array.from(new Set(arr)).sort(function(a,b){return a.localeCompare(b,undefined,{sensitivity:"accent",numeric:true});});
      }).catch(function(){return [];});
  }

  function initOne(root){
    if(!root) root=document;

    // Busca SOLO dentro del root (wizard panel o documento)
    var iYear = root.querySelector("#bb-year"),
        sYear = root.querySelector("#bb-year-suggest");
    var iMake = root.querySelector("#bb-make"),
        sMake = root.querySelector("#bb-make-suggest");
    var iModel = root.querySelector("#bb-model"),
        sModel = root.querySelector("#bb-model-suggest");
    var iColor = root.querySelector("#bb-color"),
        sColor = root.querySelector("#bb-color-suggest");

    // Si no está el formulario, no hacemos nada
    if(!iYear || !iMake || !iModel || !iColor) return;

    // Sugerencias
    mountSuggest(root, iYear,  sYear,  function(q,done){ done(filterContains(years,q)); }, iMake);
    mountSuggest(root, iMake,  sMake,  function(q,done){
      q=(q||"").trim();
      if(!q){ done(popularMakes); return; }
      if(vpAllMakes){ done(rankMakes(vpAllMakes,q)); return; }
      fetchAllMakes().then(function(all){ done(rankMakes(all,q)); }).catch(function(){ done(popularMakes); });
    }, iModel);
    mountSuggest(root, iModel, sModel, function(q,done){
      var mk=(iMake.value||"").trim(), yr=(iYear.value||"").trim();
      if(!mk||!yr){ done([]); return; }
      fetchModels(mk,yr).then(function(list){
        q=(q||"").toLowerCase().trim();
        if(!q){ done(list.slice(0,50)); return; }
        var exact=[],starts=[],contains=[];
        list.forEach(function(m){var ml=m.toLowerCase(); if(ml===q) exact.push(m); else if(ml.indexOf(q)===0) starts.push(m); else if(ml.indexOf(q)>0) contains.push(m);});
        done(exact.concat(starts,contains).slice(0,50));
      }).catch(function(){ done([]); });
    }, iColor);
    mountSuggest(root, iColor, sColor, function(q,done){ done(filterContains(popularColors,q)); }, null);

    // Help toggle
    var btnHelp=root.querySelector("#bb-help");
    var helpMsg=root.querySelector("#bb-help-msg");
    if(btnHelp && helpMsg){
      btnHelp.addEventListener("click",function(){
        helpMsg.style.display=(helpMsg.style.display==="none"||!helpMsg.style.display)?"block":"none";
      });
    }

    // Reset model cuando cambian year/make
    iYear.addEventListener("input",function(){ if(iModel && sModel){ iModel.value=""; sModel.style.display="none"; }});
    iMake.addEventListener("input",function(){ if(iModel && sModel){ iModel.value=""; sModel.style.display="none"; }});
  }

  // Expone función global
  window.BB_PICKER_INIT = function(scope){ initOne(scope||document); };

  // Auto-init al cargar la página (soporta usar el picker solo o dentro del wizard)
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function(){ initOne(document); });
  } else {
    initOne(document);
  }
})();
