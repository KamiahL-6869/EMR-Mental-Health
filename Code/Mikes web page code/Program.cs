using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text.Json;

var builder = WebApplication.CreateBuilder(args);

// Add services to the container.
builder.Services.AddRazorPages();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    // The default HSTS value is 30 days. You may want to change this for production scenarios, see https://aka.ms/aspnetcore-hsts.
    app.UseHsts();
}

app.UseHttpsRedirection();

app.UseRouting();

app.UseAuthorization();

// Enable serving static files from wwwroot
app.UseStaticFiles();

// Serve the HTML page from the web root (wwwroot/HTMLPage1.html)
app.MapGet("/", () => Results.File(Path.Combine(app.Environment.WebRootPath, "HTMLPage1.html"), "text/html"));

// Simple in-memory store for Communications. Replace with persistent store and auth checks.
var communications = new List<JsonElement>();

app.MapGet("/api/patients/{pid}/communications", (string pid) => Results.Ok(communications));

app.MapPost("/api/patients/{pid}/communications", async (string pid, HttpRequest req) =>
{
    try
    {
        using var doc = await JsonDocument.ParseAsync(req.Body);
        var el = doc.RootElement.Clone();
        communications.Add(el);
        var id = el.TryGetProperty("id", out var idp) ? idp.GetString() ?? Guid.NewGuid().ToString() : Guid.NewGuid().ToString();
        return Results.Created($"/api/patients/{pid}/communications/{id}", el);
    }
    catch
    {
        return Results.BadRequest();
    }
});

app.MapStaticAssets();
app.MapRazorPages()
   .WithStaticAssets();

await app.StartAsync();

// Get the actual URL from the running application
var serverAddresses = app.Urls;
var url = serverAddresses.FirstOrDefault() ?? "http://localhost:5000";

// Open browser in a cross-platform way
try
{
    var psi = new ProcessStartInfo();
    if (RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
    {
        psi.FileName = "cmd";
        psi.Arguments = $"/c start {url}";
    }
    else if (RuntimeInformation.IsOSPlatform(OSPlatform.OSX))
    {
        psi.FileName = "open";
        psi.Arguments = url;
    }
    else // Linux
    {
        psi.FileName = "xdg-open";
        psi.Arguments = url;
    }
    psi.UseShellExecute = true;
    Process.Start(psi);
}
catch
{
    // Browser opening failed, but app is still running
    Console.WriteLine($"Application is running at {url}");
}

await app.WaitForShutdownAsync();
