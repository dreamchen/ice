
Dim vs2013CsPath
Dim vs2013CppPath

vs2013CsPath = Session.Property("VS_2013_CS_TEMPLATE_DIR")
vs2013CppPath = Session.Property("VS_2013_CPP_TEMPLATE_DIR")

Session.Property("VS_2013_CS_TEMPLATE_DIR_MOD") = Replace(vs2013CsPath, "\.\", "\")
Session.Property("VS_2013_CPP_TEMPLATE_DIR_MOD") = Replace(vs2013CppPath, "\.\", "\")